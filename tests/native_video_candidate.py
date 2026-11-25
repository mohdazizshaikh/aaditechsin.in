"""Synthetic native-motion adapter tests; no real model or GPU."""
import importlib.util
import json
from pathlib import Path
import tempfile
from PIL import Image,ImageDraw
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("native",root/"workers/native-video-candidate.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def blocked(fn):
    try:fn()
    except (ValueError,OSError):return
    raise AssertionError("Expected refusal")
job={"version":1,"task_id":1,"revision":1,"channel":"linkedin","service_title":"Infrastructure Services","caption":"Plan your network.","visual_brief":"Illustrative equipment","video_brief":"Planning checklist","dimensions":[1200,628]}
doc={"version":1,"jobs":[job]}
services=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]
cases=0
for service in services:
    for channel,dims in m.media.PROFILES.items():
        j={**job,"service_title":service["title"],"channel":channel,"dimensions":list(dims)}
        a=m.plan({"version":1,"jobs":[j]},"a"*64,"b"*64,1)
        assert a["jobs"]==1 and a["plan_sha256"]!=m.plan({"version":1,"jobs":[j]},"a"*64,"b"*64,2)["plan_sha256"]
        cases+=1
assert cases==126
index={"_class_name":"CogVideoXPipeline",**{k:[v[0],sorted(v[1])[0]] for k,v in m.COMPONENTS.items()}}
m.model_index(index)
for k in m.COMPONENTS:
    blocked(lambda:m.model_index({**index,k:["unknown","Custom"]}))
blocked(lambda:m.model_index({**index,"_class_name":"OtherPipeline"}))
font=Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp);model=p/"model";model.mkdir();(model/"transformer").mkdir()
    (model/"model_index.json").write_text(json.dumps(index))
    weights=model/"transformer/model.safetensors";weights.write_bytes(b"synthetic not real weights")
    h=m.model_inventory(model);assert m.exact_hash(h)
    weights.write_bytes(b"changed weights");assert m.model_inventory(model)!=h
    bad=model/"bad.bin";bad.write_bytes(b"synthetic");blocked(lambda:m.model_inventory(model));bad.unlink()
    alias=model/"alias.json";alias.symlink_to(model/"model_index.json");blocked(lambda:m.model_inventory(model));alias.unlink()
    model_sha=m.model_inventory(model);font_sha=m.font_digest(font)
    a=m.plan(doc,model_sha,font_sha,42)
    output=p/"out";output.mkdir(mode=0o700)
    blocked(lambda:m.render_batch(doc,"0"*64,model,font,42,output))
    assert not list(output.iterdir())
    static=[Image.new("RGB",(720,480),"blue")]*49
    blocked(lambda:m.validate_frames(static))
    blocked(lambda:m.validate_frames(static[:-1]))
    frames=[]
    for i in range(49):
        f=Image.new("RGB",(720,480),(i*4,60,100))
        ImageDraw.Draw(f).rectangle((i*8,40,i*8+80,200),fill="white");frames.append(f)
    out=m.render_batch(doc,a["plan_sha256"],model,font,42,output,infer=lambda *_:frames)
    idx=json.loads((out/"review-index.json").read_text());assert idx["scope"]=="native-video-review-only"
    bundle=out/idx["candidates"][0]["directory"]
    review=json.loads((bundle/"review.json").read_text())
    assert review["candidate_sha256"]==m.media.sha(bundle/"candidate.mp4")
    assert review["model_sha256"]==model_sha and review["seed"]==42
    assert not (bundle/"manifest.json").exists() and not (out/"batch-index.json").exists()
    idx_hash=m.media.sha(out/"review-index.json")
    verified=m.verify_batch(doc,model_sha,font_sha,42,a["plan_sha256"],out,idx_hash)
    assert verified["scope"]=="verified-native-candidates" and len(verified["candidates"])==1
    row=verified["candidates"][0]
    decisions={"version":1,"index_sha256":idx_hash,"decisions":[{**row,"decision":"forward_for_editorial_review","reason":"ready_for_editorial_review"}]}
    report=m.review_decisions(verified,decisions)
    assert report["report"]["publication_authorized"] is False and m.exact_hash(report["sha256"])
    rejected={**row,"decision":"reject","reason":"visual_artifacts"}
    assert m.review_decisions(verified,{**decisions,"decisions":[rejected]})["report"]["decisions"]==[rejected]
    decision_cases=0
    for patch in ({"task_id":True},{"task_id":2},{"revision":2},{"review_sha256":"0"*64},{"decision":"publish"},{"reason":"wrong_service"},{"reason":[]},{"decision":"reject"}):
        blocked(lambda:m.review_decisions(verified,{**decisions,"decisions":[{**decisions["decisions"][0],**patch}]}));decision_cases+=1
    for reason in m.REVIEW_REASONS-{"ready_for_editorial_review"}:
        assert m.review_decisions(verified,{**decisions,"decisions":[{**rejected,"reason":reason}]})["report"]["decisions"][0]["reason"]==reason;decision_cases+=1
    blocked(lambda:m.review_decisions(verified,{**decisions,"index_sha256":"0"*64}))
    blocked(lambda:m.review_decisions(verified,{**decisions,"decisions":[]}))
    blocked(lambda:m.verify_batch(doc,model_sha,font_sha,43,a["plan_sha256"],out,idx_hash))
    blocked(lambda:m.verify_batch(doc,model_sha,font_sha,42,a["plan_sha256"],out,"0"*64))
    extra=bundle/"extra.txt";extra.write_text("unexpected")
    blocked(lambda:m.verify_batch(doc,model_sha,font_sha,42,a["plan_sha256"],out,idx_hash));extra.unlink()
    poster=bundle/"poster.png";original=poster.read_bytes();poster.write_bytes(original+b"tampered")
    blocked(lambda:m.verify_batch(doc,model_sha,font_sha,42,a["plan_sha256"],out,idx_hash));poster.write_bytes(original)
    poster.chmod(0o644);blocked(lambda:m.verify_batch(doc,model_sha,font_sha,42,a["plan_sha256"],out,idx_hash));poster.chmod(0o600)
    assert decision_cases==13
    blocked(lambda:m.render_batch(doc,a["plan_sha256"],model,font,42,output,infer=lambda *_:static))
    assert list(output.iterdir())==[out]
print("Native video candidate: 126 service/channel plan cases, model identity and actual synthetic motion encode PASS")
