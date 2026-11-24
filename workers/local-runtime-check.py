"""Explicit local model and media runtime checks. No downloads, publish or business inputs."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import urllib.request
import sys

spec=importlib.util.spec_from_file_location("local_creative",Path(__file__).with_name("local-creative.py"))
local=importlib.util.module_from_spec(spec);spec.loader.exec_module(local)

def model_identity(model,expected,response):
    if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:-]{0,99}",model) or "cloud" in model.lower() or not re.fullmatch(r"[a-f0-9]{64}",expected):
        raise ValueError("Exact local tag and digest required")
    rows=response.get("models")
    if not isinstance(rows,list) or len(rows)>2000:raise ValueError("Invalid inventory")
    found=[r for r in rows if isinstance(r,dict) and r.get("name")==model and r.get("model",model)==model]
    if len(found)!=1 or found[0].get("digest")!=expected or type(found[0].get("size")) is not int or found[0]["size"]<=0 or found[0].get("remote_model") or found[0].get("remote_host"):
        raise ValueError("Model identity not verified")
    return True

def tags():
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),local.NoRedirect())
    with opener.open("http://127.0.0.1:11434/api/tags",timeout=10) as r:
        raw=r.read(262145)
    if len(raw)>262144:raise ValueError("Inventory too large")
    return json.loads(raw)

def smoke(model,transport=local.request_local):
    response=transport({"model":model,"stream":False,"format":{"type":"object","properties":{"status":{"type":"string","enum":["ready"]}},"required":["status"],"additionalProperties":False},"messages":[{"role":"user","content":"Return exactly a JSON object with status ready. This is a synthetic test."}],"options":{"temperature":0,"num_predict":32}})
    if response.get("model")!=model or response.get("done") is not True or response.get("message",{}).get("tool_calls") or json.loads(response["message"]["content"])!={"status":"ready"}:
        raise ValueError("Structured inference not verified")
    return True

def media_smoke():
    ffmpeg=shutil.which("ffmpeg");ffprobe=shutil.which("ffprobe")
    if not ffmpeg or not ffprobe:raise ValueError("Trusted media tools required")
    with tempfile.TemporaryDirectory(prefix="aaditechs-runtime-") as tmp:
        p=Path(tmp)/"synthetic.mp4"
        subprocess.run([ffmpeg,"-nostdin","-v","error","-f","lavfi","-i","color=c=black:s=320x240:r=24","-t","1","-c:v","libx264","-pix_fmt","yuv420p","-an",str(p)],check=True,timeout=30,capture_output=True)
        r=json.loads(subprocess.check_output([ffprobe,"-v","error","-show_entries","stream=codec_name,codec_type,width,height:format=duration","-of","json",str(p)],timeout=15))
        if len(r["streams"])!=1 or r["streams"][0]!={"codec_name":"h264","codec_type":"video","width":320,"height":240} or not 0.95<=float(r["format"]["duration"])<=1.05:
            raise ValueError("Synthetic codec mismatch")
        subprocess.run([ffmpeg,"-nostdin","-v","error","-xerror","-i",str(p),"-f","null","-"],check=True,timeout=30,capture_output=True)
    return True

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--model",required=True);p.add_argument("--digest",required=True)
    p.add_argument("--synthetic-inference",action="store_true");p.add_argument("--media-smoke",action="store_true")
    a=p.parse_args()
    try:
        model_identity(a.model,a.digest,tags())
        if a.synthetic_inference:
            smoke(a.model)
            model_identity(a.model,a.digest,tags())
        if a.media_smoke:media_smoke()
        print(json.dumps({"local_inventory_match":True,"synthetic_inference":"passed" if a.synthetic_inference else "not_run","synthetic_h264":"passed" if a.media_smoke else "not_run","production_acceptance":False}))
        return 0
    except Exception:
        print("BLOCKED: local model identity, runtime or codec check failed.",file=sys.stderr);return 1
if __name__=="__main__":sys.exit(main())
