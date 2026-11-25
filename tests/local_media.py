"""Synthetic media rendering and tamper tests, no actual diffusion model."""
import importlib.util
from pathlib import Path
import tempfile
import json
import subprocess
from PIL import Image
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("media",root/"workers/local-media.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
font=Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
job={"version":1,"task_id":1,"revision":2,"channel":"linkedin","service_title":"Infrastructure Services","caption":"Plan your network.","visual_brief":"Illustrative equipment","video_brief":"Planning checklist","dimensions":[1200,628]}
assert len(m.PROFILES)==9
try:
    m.validate({**job,"dimensions":[1,1]})
except ValueError:
    pass
else:
    raise AssertionError("Invalid profile accepted")
try:
    m.private_root(root/"public_html")
except ValueError:
    pass
else:
    raise AssertionError("Public root accepted")
with tempfile.TemporaryDirectory() as d:
    out=m.render(job,Path(d),font,"template")
    assert Image.open(out/"creative.png").size==(1200,628)
    meta=json.loads((out/"manifest.json").read_text())
    assert meta["renderer"]=="template" and m.verify(out)==m.sha(out/"manifest.json")
    probe=subprocess.check_output(["ffprobe","-v","error","-select_streams","v:0","-show_entries","stream=width,height,codec_name","-of","json",str(out/"creative.mp4")])
    assert json.loads(probe)["streams"]==[{"codec_name":"h264","width":1200,"height":628}]
    with (out/"creative.png").open("ab") as f:
        f.write(b"tampered")
    try:
        m.verify(out)
    except ValueError:
        pass
    else:
        raise AssertionError("Tampering undetected")
    fake=m.render(job,Path(d),font,"local-diffusion",generate=lambda j,p:Image.new("RGB",(768,768),"blue"))
    assert json.loads((fake/"manifest.json").read_text())["renderer"]=="local-diffusion"
    meta=json.loads((fake/"manifest.json").read_text()); meta["width"]=1
    (fake/"manifest.json").write_text(json.dumps(meta))
    try: m.verify(fake)
    except ValueError: pass
    else: raise AssertionError("Manifest profile mismatch accepted")
print("Actual synthetic PNG/MP4 render, profile, private root, fake AI adapter, integrity: PASS")

# Full catalog/profile validation matrix; no actual AI inference.
services=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]
cases=0
for service in services:
    for channel,dimensions in m.PROFILES.items():
        candidate={**job,"service_title":service["title"],"channel":channel,"dimensions":list(dimensions)}
        assert m.validate(candidate)==candidate
        cases+=1
assert cases==126
def blocked(fn):
    try:fn()
    except (ValueError,OSError):return
    raise AssertionError("Expected denial")
for patch in ({"version":True},{"service_title":"Unlisted service"},{"caption":"100% guaranteed recovery"},{"channel":"x","caption":"x"*281},{"channel":"threads","dimensions":[1080,1080],"caption":"x"*501}):
    blocked(lambda:m.validate({**job,**patch}))
doc={"version":1,"jobs":[job,{**job,"task_id":2,"channel":"instagram","dimensions":[1080,1920]}]}
plan=m.batch_plan(doc)
assert plan["jobs"]==2 and len(plan["sha256"])==64
assert m.batch_plan({"jobs":doc["jobs"],"version":1})==plan
for bad in ({"version":1,"jobs":[]},{"version":1,"jobs":[job]*13},{"version":1,"jobs":[job,{**job,"revision":3}]}):
    blocked(lambda:m.batch_plan(bad))
with tempfile.TemporaryDirectory() as d:
    d=Path(d)
    blocked(lambda:m.render_batch(doc,"0"*64,d,font,"template"))
    assert not list(d.iterdir())
    # Real render and verify of portrait and landscape in one reviewed batch.
    out=m.render_batch(doc,plan["sha256"],d,font,"template")
    index=json.loads((out/"batch-index.json").read_text())
    assert index["plan_sha256"]==plan["sha256"] and len(index["bundles"])==2
    for row in index["bundles"]:
        directory=out/row["directory"]
        assert m.verify(directory)==row["manifest_sha256"]
    # Verify private file guards without changing generated assets.
    directory=out/index["bundles"][0]["directory"]
    manifest=directory/"manifest.json";raw=manifest.read_bytes()
    manifest.chmod(0o644);blocked(lambda:m.verify(directory));manifest.chmod(0o600)
    meta=json.loads(raw)
    for patch in ({"extra":"x"},{"version":True},{"renderer":"unknown"},{"task_id":0},{"image_sha256":"bad"}):
        manifest.write_text(json.dumps({**meta,**patch}));blocked(lambda:m.verify(directory))
    manifest.write_bytes(raw)
    manifest.write_text(raw.decode().replace('{','{"version":1,',1));blocked(lambda:m.verify(directory))
    manifest.write_bytes(raw)
    alias=d/"alias";alias.symlink_to(directory,target_is_directory=True)
    blocked(lambda:m.verify(alias));alias.unlink()
    # Fault injection: second render fails and the entire new staging directory is removed.
    before=set(d.iterdir());calls=[]
    def fail_second(j,stage,*args):
        calls.append(j["task_id"])
        if len(calls)==2:raise ValueError("synthetic failure")
        f=stage/"synthetic";f.mkdir()
        return f
    blocked(lambda:m.render_batch(doc,plan["sha256"],d,font,"template",render_one=fail_second,verify_one=lambda p:"a"*64))
    assert calls==[1,2] and set(d.iterdir())==before
print(f"Media validation matrix: {cases}/126; reviewed batch and private bundle safeguards PASS")
