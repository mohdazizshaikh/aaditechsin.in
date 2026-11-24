"""Test the local model contract without a real Ollama runtime or network."""
import importlib.util
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("local_creative",root/"workers/local-creative.py")
m=importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
service=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"][0]
job={"version":1,"task_id":1,"revision":2,"service":service,"channel":"linkedin","objective":"education"}
draft={"caption":"Plan an office network with a requirements assessment.","visual_brief":"Illustrative network diagram.","video_brief":"Show three planning steps.","review_notes":"Verify technical claims."}
def fake(payload):
    assert payload["stream"] is False and "tools" not in payload and payload["model"]=="local-test:1b"
    return {"done":True,"message":{"content":json.dumps(draft)}}
result=m.generate(job,"local-test:1b",fake)
assert result["task_id"]==1 and result["revision"]==2 and result["caption"]==draft["caption"]
def bad(job,model,transport):
    try:
        m.generate(job,model,transport)
    except ValueError:
        return
    raise AssertionError("Invalid input accepted")
bad(job,"model-cloud",fake)
bad({**job,"channel":"invalid"},"local-test:1b",fake)
bad(job,"local-test:1b",lambda p:{"done":True,"message":{"content":json.dumps({**draft,"caption":"100% guaranteed recovery"})}})
bad({**job,"channel":"x"},"local-test:1b",lambda p:{"done":True,"message":{"content":json.dumps({**draft,"caption":"x"*281})}})
print("Local model structured output, channel length and safety contract: PASS (fake transport)")
