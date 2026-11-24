import importlib.util,json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("expert",root/"workers/local-expert.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
catalog=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]
kb=json.loads((root/"public_html/assets/expert-knowledge.json").read_text())["services"]
assert len(catalog)==len(kb)==14 and {s["id"] for s in catalog}==set(kb)
def fake(payload):
    assert "tools" not in payload and payload["stream"] is False
    return {"done":True,"message":{"content":json.dumps({"assessment":"Assess requirements.","questions":"How many locations?","next_steps":"Arrange specialist review."})}}
for s in catalog:
    j={"version":1,"task_id":1,"revision":1,"service_id":s["id"],"intent":"assessment","service":s,"knowledge":kb[s["id"]]}
    assert m.generate(j,"local-test:1b",fake)["revision"]==1
try:m.generate({**j,"knowledge":{"intake":"untrusted"}}, "local-test:1b",fake)
except ValueError:pass
else:raise AssertionError("Tampered knowledge accepted")
print("Expert knowledge coverage and structured mock model: PASS")

for intent in ("planning","escalation"):
    for s in catalog:
        j={"version":1,"task_id":1,"revision":1,"service_id":s["id"],"intent":intent,"service":s,"knowledge":kb[s["id"]]}
        assert m.generate(j,"local-test:1b",fake)["revision"]==1
print("28 additional expert service/intent cases: PASS (synthetic)")
