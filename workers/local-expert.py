"""Private structured technical draft generator using the existing loopback Ollama transport."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("local_creative",Path(__file__).with_name("local-creative.py"))
local=importlib.util.module_from_spec(spec);spec.loader.exec_module(local)
limits={"assessment":500,"questions":400,"next_steps":500}

def generate(job,model,transport=local.request_local):
    if not isinstance(job,dict) or set(job)!={"version","task_id","revision","service_id","intent","service","knowledge"} or job["version"]!=1 or any(type(job[k]) is not int or job[k]<1 for k in ("task_id","revision")):
        raise ValueError("Invalid job")
    catalog={s["id"]:s for s in json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]}
    kb=json.loads((root/"public_html/assets/expert-knowledge.json").read_text())["services"]
    sid=job["service_id"]
    if sid not in catalog or job["service"]!=catalog[sid] or job["knowledge"]!=kb[sid] or job["intent"] not in ("assessment","planning","escalation"):
        raise ValueError("Only exact local catalog and predefined intent allowed")
    local.validate_model(model)
    schema={"type":"object","properties":{k:{"type":"string","maxLength":n} for k,n in limits.items()},"required":list(limits),"additionalProperties":False}
    system="Draft a private technical planning assessment, not a final engineering design. Use the supplied catalog as the only business facts and supplied guidance as the scope. Ask non-sensitive requirements questions. Do not provide exact specs, pricing, credentials, destructive repair, exploit steps, dangerous electrical or rigging instructions, guarantees, personal data or external links. Escalate uncertainties to an authorized human engineer. Do not execute anything. Return JSON in English within UTF-8 byte limits "+json.dumps(limits)
    r=transport({"model":model,"stream":False,"format":schema,"messages":[{"role":"system","content":system},{"role":"user","content":json.dumps({"service":job["service"],"guidance":job["knowledge"],"intent":job["intent"]})}],"options":{"temperature":0.2,"num_predict":800}})
    if not isinstance(r,dict) or r.get("done") is not True or not isinstance(r.get("message"),dict) or r["message"].get("tool_calls") or ("model" in r and r["model"]!=model):
        raise ValueError("Incomplete, mismatched or tool response")
    d=local.strict_json(r["message"]["content"])
    if not isinstance(d,dict) or set(d)!=set(limits):
        raise ValueError("Invalid output")
    for k,n in limits.items():
        s=d[k]
        if not isinstance(s,str) or not s.strip() or len(s.encode())>n or local.CLAIMS.search(s) or re.search(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]",s):
            raise ValueError("Invalid draft")
    return {"version":1,"task_id":job["task_id"],"revision":job["revision"],"model":model,**d}

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument("--model",required=True);a=p.parse_args()
    try:
        raw=sys.stdin.buffer.read(16385)
        if len(raw)>16384:raise ValueError("Oversized job")
        print(json.dumps(generate(local.strict_json(raw),a.model),ensure_ascii=False))
        return 0
    except Exception:
        print("BLOCKED: invalid expert job, model or draft.",file=sys.stderr)
        return 1
if __name__=="__main__":sys.exit(main())
