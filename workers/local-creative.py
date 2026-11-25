"""Local-only Ollama creative draft generator. No publish, network tools or DB access."""
import argparse
import json
from pathlib import Path
import re
import sys
import urllib.request

URL = "http://127.0.0.1:11434/api/chat"
CHANNELS = {"facebook","instagram","linkedin","youtube","google-business","x","threads","telegram","blog"}
OBJECTIVES = {"education","awareness","enquiry","case-planning"}
LIMITS = {"caption":900,"visual_brief":400,"video_brief":400,"review_notes":160}
CLAIMS = re.compile(r"100\s*%|guaranteed|zero\s*risk|number\s*one|#1\b|certified|authori[sz]ed\s+partner|https?://|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d .()-]{8,}\d", re.I)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        raise ValueError("Redirect refused")

def request_local(payload):
    # No proxy, arbitrary destination, cloud endpoint, tools or redirects.
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    req = urllib.request.Request(URL, data=json.dumps(payload).encode(), headers={"Content-Type":"application/json"}, method="POST")
    with opener.open(req, timeout=180) as response:
        body = response.read(262145)
    if len(body)>262144:
        raise ValueError("Response too large")
    return strict_json(body)

def strict_json(raw):
    def unique(pairs):
        out={}
        for k,v in pairs:
            if k in out:
                raise ValueError("Duplicate JSON field")
            out[k]=v
        return out
    return json.loads(raw,object_pairs_hook=unique)

def validate_model(model):
    if not isinstance(model,str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:-]{0,99}",model) or "cloud" in model.lower():
        raise ValueError("Use an installed local model tag (no cloud tag)")
    return model

def validate_job(job):
    if not isinstance(job,dict) or set(job)!={"version","task_id","revision","service","channel","objective"} or type(job["version"]) is not int or job["version"]!=1:
        raise ValueError("Invalid job")
    if any(type(job[k]) is not int or job[k]<1 for k in ("task_id","revision")):
        raise ValueError("Invalid identifiers")
    if not isinstance(job['channel'],str) or not isinstance(job['objective'],str) or job['channel'] not in CHANNELS or job['objective'] not in OBJECTIVES:
        raise ValueError("Invalid channel/objective")
    svc=job["service"]
    if not isinstance(svc,dict) or set(svc)!={"id","title","description","category","capabilities"}:
        raise ValueError("Invalid service")
    if not isinstance(svc["capabilities"],list) or not 1<=len(svc["capabilities"])<=10:
        raise ValueError("Invalid capabilities")
    if any(not isinstance(s,str) or not s.strip() or len(s.encode())>500 for s in [svc["id"],svc["title"],svc["description"],svc["category"],*svc["capabilities"]]):
        raise ValueError("Invalid catalog text")
    catalog=strict_json((Path(__file__).resolve().parents[1]/"public_html/assets/service-registry.json").read_text(encoding="utf-8"))["services"]
    if sum(s==svc for s in catalog)!=1:
        raise ValueError("Only an exact local catalog entry is permitted")
    return svc

def generate(job, model, transport=request_local):
    svc=validate_job(job)
    validate_model(model)
    limits=dict(LIMITS)
    limits["caption"]={"x":280,"threads":500}.get(job["channel"],900)
    system=("You are Aaditechs' draft-only technical content editor. Use only the supplied service catalog as business facts. "
            "Write concise professional English suitable for the selected channel and objective. "
            "No prices, fabricated case studies, rankings, certifications, guarantees, personal details or links. "
            "Describe partner-coordinated delivery honestly. For data recovery recommend assessment, never destructive repair or guaranteed recovery. "
            "Return a caption, a visual design brief, a short video storyboard brief, and human review notes. "
            "No tool calls or actions. Keep each field within these UTF-8 byte limits: "+json.dumps(limits))
    schema={"type":"object","properties":{k:{"type":"string","maxLength":v} for k,v in limits.items()},"required":list(limits),"additionalProperties":False}
    result=transport({"model":model,"stream":False,"format":schema,"messages":[{"role":"system","content":system},{"role":"user","content":json.dumps({"service":svc,"channel":job["channel"],"objective":job["objective"]},ensure_ascii=False)}],"options":{"temperature":0.3,"num_predict":1200}})
    if not isinstance(result,dict) or result.get("done") is not True or not isinstance(result.get("message"),dict) or result["message"].get("tool_calls") or ("model" in result and result["model"]!=model):
        raise ValueError("Incomplete, mismatched or tool-call response")
    data=strict_json(result["message"]["content"])
    if not isinstance(data,dict) or set(data)!=set(limits):
        raise ValueError("Invalid structured output")
    for key,limit in limits.items():
        value=data[key]
        if not isinstance(value,str) or not value.strip() or len(value.encode())>limit or re.search(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]",value):
            raise ValueError("Invalid output field")
    if CLAIMS.search(" ".join(data[k] for k in ("caption","visual_brief","video_brief"))):
        raise ValueError("Unsupported claim or contact detail; human review required")
    return {"version":1,"task_id":job["task_id"],"revision":job["revision"],"model":model,**data}

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--model",required=True,help="An already installed local Ollama model")
    args=p.parse_args()
    try:
        raw=sys.stdin.buffer.read(16385)
        if len(raw)>16384:
            raise ValueError("Job too large")
        print(json.dumps(generate(strict_json(raw),args.model),ensure_ascii=False))
    except Exception:
        print("BLOCKED: invalid job, unavailable local model or invalid generated draft.",file=sys.stderr)
        return 1
    return 0

if __name__=="__main__":
    sys.exit(main())
