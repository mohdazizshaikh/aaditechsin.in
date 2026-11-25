import importlib.util
from pathlib import Path
spec=importlib.util.spec_from_file_location("runtime",Path(__file__).resolve().parents[1]/"workers/local-runtime-check.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
tag="synthetic:1b";digest="a"*64
row={"name":tag,"model":tag,"digest":digest,"size":100}
assert m.model_identity(tag,digest,{"models":[row]})
def blocked(fn):
    try:fn()
    except ValueError:return
    raise AssertionError("Expected refusal")
blocked(lambda:m.model_identity(tag,"b"*64,{"models":[row]}))
blocked(lambda:m.model_identity(tag,digest,{"models":[row,row]}))
blocked(lambda:m.model_identity(tag,digest,{"models":[dict(row,remote_host="cloud")]}))
blocked(lambda:m.model_identity("synthetic-cloud",digest,{"models":[row]}))
assert m.smoke(tag,lambda payload:{"model":tag,"done":True,"message":{"content":'{"status":"ready"}'}})
blocked(lambda:m.smoke(tag,lambda payload:{"model":tag,"done":True,"message":{"content":'{"status":"wrong"}'}}))
assert m.media_smoke()
print("Synthetic local model identity/inference mocks and real H264 codec smoke: PASS")
