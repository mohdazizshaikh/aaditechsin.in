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
        return json.loads(body)
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

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--endpoint",required=True)
    p.add_argument("--model",required=True)
    a=p.parse_args()
    try:
        status=run_once(client(a.endpoint,os.environ.get("AADITECHS_CREATIVE_WORKER_TOKEN","")),a.model)
        print(status)
        return 1 if status=="failed" else 0
    except Exception:
        print("BLOCKED: worker configuration, lease, generation or transport unavailable.",file=sys.stderr)
        return 1

if __name__=="__main__":
    sys.exit(main())
