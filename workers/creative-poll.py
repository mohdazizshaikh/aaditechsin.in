"""One catalog-only creative job per invocation; schedule locally if approved."""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

spec=importlib.util.spec_from_file_location("local_creative",Path(__file__).with_name("local-creative.py"))
local=importlib.util.module_from_spec(spec)
spec.loader.exec_module(local)

def validate_endpoint(url):
    p=urllib.parse.urlsplit(url)
    if p.scheme!="https" or not p.hostname or p.username is not None or p.password is not None or p.query or p.fragment or p.path!="/api/creative-transport.php" or p.port not in (None,443):
        raise ValueError("Use the exact trusted HTTPS staging endpoint")
    return url

def client(url,token):
    validate_endpoint(url)
    if not re.fullmatch(r"[a-f0-9]{64}",token):
        raise ValueError("Missing independent worker token")
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),local.NoRedirect())
    def post(payload):
        raw=json.dumps(payload,ensure_ascii=False).encode()
        if len(raw)>16384:
            raise ValueError("Request too large")
        req=urllib.request.Request(url,data=raw,headers={"Content-Type":"application/json","X-Aaditechs-Worker-Token":token},method="POST")
        with opener.open(req,timeout=30) as response:
            body=response.read(16385)
        if len(body)>16384:
            raise ValueError("Response too large")
        return local.strict_json(body)
    return post

def run_once(post,model,generate=local.generate):
    claim=post({"op":"claim"})
    if claim=={"status":"empty"}:
        return "empty"
    if not isinstance(claim,dict) or set(claim)!={"status","lease","job"} or claim["status"]!="leased" or not isinstance(claim["lease"],str) or not re.fullmatch(r"[a-f0-9]{64}",claim["lease"]):
        raise ValueError("Invalid claim")
    job=claim["job"]
    if not isinstance(job,dict) or any(type(job.get(k)) is not int or job[k]<1 for k in ("task_id","revision")):
        raise ValueError("Invalid job")
    scope={"task_id":job["task_id"],"revision":job["revision"],"lease":claim["lease"]}
    try:
        result=generate(job,model)
    except Exception:
        # Do not include model errors, prompts or secrets in the failure report.
        payload={"op":"fail",**scope}
        expected="failed"
    else:
        payload={"op":"complete",**scope,"result":result}
        expected="pending_review"
    # Only retry the identical completion/failure payload, never claim on uncertain transport.
    for attempt in range(2):
        try:
            reply=post(payload)
            break
        except urllib.error.HTTPError as e:
            if attempt or e.code not in (502,503,504):
                raise
        except (OSError,urllib.error.URLError):
            if attempt:
                raise
    if reply!={"status":expected}:
        raise ValueError("Unexpected acknowledgement")
    return expected

def run_batch(post,model,limit,generate=local.generate):
    if type(limit) is not int or not 1<=limit<=20:
        raise ValueError("Batch limit must be 1–20")
    local.validate_model(model)
    counts={"completed":0,"failed":0,"stop":"limit"}
    for _ in range(limit):
        status=run_once(post,model,generate)
        if status=="empty":
            counts["stop"]="empty"
            break
        if status=="failed":
            counts["failed"]+=1
            counts["stop"]="failed"
            break
        counts["completed"]+=1
    return counts

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--endpoint",required=True)
    p.add_argument("--model",required=True)
    p.add_argument("--model-digest",help="Independently verified installed model SHA256")
    p.add_argument("--limit",type=int,default=1,help="Maximum jobs per invocation (1–20)")
    a=p.parse_args()
    try:
        local.validate_model(a.model)
        if not 1<=a.limit<=20 or (a.limit>1 and not a.model_digest):
            raise ValueError("Bounded batch requires a pinned model")
        post=client(a.endpoint,os.environ.get("AADITECHS_CREATIVE_WORKER_TOKEN",""))
        generate=local.generate
        if a.model_digest:
            spec=importlib.util.spec_from_file_location("runtime",Path(__file__).with_name("local-runtime-check.py"))
            runtime=importlib.util.module_from_spec(spec);spec.loader.exec_module(runtime)
            generate=runtime.pinned_generator(a.model,a.model_digest)
        if a.limit==1:
            status=run_once(post,a.model,generate)
            print(status)
            return 1 if status=="failed" else 0
        counts=run_batch(post,a.model,a.limit,generate)
        print(json.dumps(counts))
        return 1 if counts["failed"] else 0
    except Exception:
        print("BLOCKED: worker configuration, lease, generation or transport unavailable.",file=sys.stderr)
        return 1

if __name__=="__main__":
    sys.exit(main())
