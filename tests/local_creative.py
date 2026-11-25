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

# Exact catalog and four-objective matrix: no real model or network.
catalog=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]
cases=0
for svc in catalog:
    for objective in sorted(m.OBJECTIVES):
        j={**job,"service":svc,"objective":objective}
        assert m.generate(j,"local-test:1b",fake)["revision"]==2
        bad({**j,"service":{**svc,"description":"Tampered catalog description"}},"local-test:1b",fake)
        cases+=1
assert cases==56
bad({**job,"version":True},"local-test:1b",fake)
bad(job,"local-test:1b",lambda p:{"done":True,"model":"different:1b","message":{"content":json.dumps(draft)}})
bad(job,"local-test:1b",lambda p:{"done":True,"message":{"content":'{"caption":"a","caption":"b"}'}})
print(f"Exact catalog matrix: {cases} accepted and {cases} tamper denials PASS")

# Batch never claims again after failure, empty queue or uncertain acknowledgement.
def batch_post_factory(states):
    calls=[]
    def post(payload):
        calls.append(payload)
        if payload["op"]=="claim":
            return states.pop(0)
        return {"status":"failed" if payload["op"]=="fail" else "pending_review"}
    return post,calls
lease={"status":"leased","lease":"a"*64,"job":job}
post,calls=batch_post_factory([lease,lease,{"status":"empty"}])
assert poll.run_batch(post,"local-test:1b",5,lambda j,model:result)=={"completed":2,"failed":0,"stop":"empty"}
assert [p["op"] for p in calls]==["claim","complete","claim","complete","claim"]
post,calls=batch_post_factory([lease,lease])
def fail_generation(*args):raise ValueError("Synthetic")
assert poll.run_batch(post,"local-test:1b",5,fail_generation)=={"completed":0,"failed":1,"stop":"failed"}
assert [p["op"] for p in calls]==["claim","fail"]
post,calls=batch_post_factory([lease,lease])
assert poll.run_batch(post,"local-test:1b",1,lambda j,model:result)=={"completed":1,"failed":0,"stop":"limit"}
assert len(calls)==2
for limit in (0,21,True):
    try:poll.run_batch(lambda p:(_ for _ in ()).throw(AssertionError("unexpected claim")),"local-test:1b",limit)
    except ValueError:pass
    else:raise AssertionError("Bad limit")
calls=[]
def uncertain(payload):
    calls.append(payload)
    if payload["op"]=="claim":return lease
    raise OSError("Lost acknowledgement")
try:poll.run_batch(uncertain,"local-test:1b",5,lambda j,model:result)
except OSError:pass
else:raise AssertionError("Uncertain completion accepted")
assert [p["op"] for p in calls]==["claim","complete","complete"] and calls[1]==calls[2]

# Model identity check before claims and before/after each generation.
rs=importlib.util.spec_from_file_location("runtime",root/"workers/local-runtime-check.py")
runtime=importlib.util.module_from_spec(rs);rs.loader.exec_module(runtime)
tag="local-test:1b";digest="a"*64
def inventory():return {"models":[{"name":tag,"model":tag,"digest":digest,"size":1}]}
def pinned_response(payload):return {**fake(payload),"model":tag}
gen=runtime.pinned_generator(tag,digest,inventory,pinned_response)
assert gen(job,tag)["model"]==tag
for transport in (fake,lambda p:{**fake(p),"model":"different:1b"}):
    gen=runtime.pinned_generator(tag,digest,inventory,transport)
    try:gen(job,tag)
    except ValueError:pass
    else:raise AssertionError("Missing/mismatched model accepted")
sequence=[inventory(),inventory(),{"models":[]}]
gen=runtime.pinned_generator(tag,digest,lambda:sequence.pop(0),pinned_response)
try:gen(job,tag)
except ValueError:pass
else:raise AssertionError("Changed inventory accepted")
print("Bounded polling, stop behavior and pinned model consistency: PASS (synthetic)")
