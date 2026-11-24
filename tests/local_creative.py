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


# Poll transport tests: no HTTP or actual model calls.
ps=importlib.util.spec_from_file_location("creative_poll",root/"workers/creative-poll.py")
poll=importlib.util.module_from_spec(ps)
ps.loader.exec_module(poll)
for bad_url in ("http://example.test/api/creative-transport.php","https://user@example.test/api/creative-transport.php","https://example.test/api/creative-transport.php?token=bad"):
    try:
        poll.validate_endpoint(bad_url)
    except ValueError:
        continue
    raise AssertionError("Unsafe endpoint accepted")
assert poll.run_once(lambda p:{"status":"empty"},"local-test:1b")== "empty"
calls=[]
def post(payload):
    calls.append(payload)
    if payload["op"]=="claim":
        return {"status":"leased","lease":"a"*64,"job":job}
    if len(calls)==2:
        raise OSError("synthetic lost acknowledgement")
    return {"status":"pending_review"}
assert poll.run_once(post,"local-test:1b",lambda j,model:result)=="pending_review"
assert len(calls)==3 and calls[1]==calls[2] and calls[1]["result"]==result
print("Synthetic creative transport retry and endpoint validation: PASS")
