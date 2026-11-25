import importlib.util
import json
import os
from pathlib import Path
import tempfile
from unittest.mock import patch

spec=importlib.util.spec_from_file_location("transfer",Path(__file__).resolve().parents[1]/"workers/media-transfer.py")
t=importlib.util.module_from_spec(spec);spec.loader.exec_module(t)
def check(ok):
    if not ok: raise AssertionError("transfer rule")
def blocked(fn):
    try: fn()
    except ValueError: return
    raise AssertionError("expected refusal")
with tempfile.TemporaryDirectory() as tmp:
    root=Path(tmp)
    def write(name,data):
        p=root/name;p.write_bytes(data);p.chmod(0o600);return p
    png=b"\x89PNG\r\n\x1a\n"+b"test"
    mp4=b"\x00\x00\x00\x20ftyp"+b"test"
    write("creative.png",png);write("creative.mp4",mp4)
    raw=json.dumps(dict(version=1,task_id=1,revision=1,channel="threads",renderer="template",width=1080,height=1080,image_sha256=t.digest(png),video_sha256=t.digest(mp4))).encode()
    write("manifest.json",raw)
    cfg=dict(enabled=True,host="staging.example.invalid",port=22,user="media",identity_file=str(write("key",b"mock")),known_hosts=str(write("known",b"mock")),remote_inbox="/private/inbox")
    calls=[]
    def fake(args,**kw):
        check("StrictHostKeyChecking=yes" in args and "BatchMode=yes" in args and "IdentityAgent=none" in args)
        check(kw["check"] and kw["timeout"]==180)
        check("cd /private/inbox\nput " in kw["input"] and kw["input"].count("rename ")==2)
        for name,data in t.bundle(root,t.digest(raw)):
            check((Path(kw["cwd"])/name).read_bytes()==data)
        calls.append(args)
    with patch.object(t.shutil,"which",return_value="/usr/bin/sftp"):
        check(len(t.deliver(cfg,root,t.digest(raw),fake))==2 and len(calls)==1)
        blocked(lambda:t.deliver(dict(cfg,enabled=False),root,t.digest(raw),fake))
        blocked(lambda:t.deliver(dict(cfg,remote_inbox="/private/../public_html"),root,t.digest(raw),fake))
        blocked(lambda:t.deliver(dict(cfg,host="bad host"),root,t.digest(raw),fake))
        blocked(lambda:t.deliver(cfg,root,"a"*64,fake))
        write("creative.mp4",b"tampered")
        blocked(lambda:t.deliver(cfg,root,t.digest(raw),fake))
        check(len(calls)==1)
print("Synthetic private SFTP and exact bundle rules: PASS")

# Channel-by-renderer integrity matrix, with header-only fixture bytes.
# Full media codec acceptance is exercised separately in local_media.py.
cases=0
with tempfile.TemporaryDirectory() as tmp:
    root=Path(tmp)
    png=b"\x89PNG\r\n\x1a\n"+b"test"
    mp4=b"\x00\x00\x00\x20ftyp"+b"test"
    def write(path,data):
        path.write_bytes(data);path.chmod(0o600)
    write(root/"creative.png",png);write(root/"creative.mp4",mp4)
    for channel,(w,h) in t.media.PROFILES.items():
        for renderer in ("template","local-diffusion"):
            m=dict(version=1,task_id=1,revision=1,channel=channel,renderer=renderer,width=w,height=h,image_sha256=t.digest(png),video_sha256=t.digest(mp4))
            raw=json.dumps(m).encode();write(root/"manifest.json",raw)
            check(len(t.bundle(root,t.digest(raw)))==2);cases+=1
            for mutation in ({"version":True},{"renderer":"unlisted"},{"channel":"unknown"},{"width":1},{"task_id":0},{"video_sha256":"invalid"}):
                raw=json.dumps({**m,**mutation}).encode();write(root/"manifest.json",raw)
                blocked(lambda:t.bundle(root,t.digest(raw)));cases+=1
assert cases==126
print(f"Media transfer contract: {cases} positive/negative profile cases PASS")

with tempfile.TemporaryDirectory() as tmp:
    root=Path(tmp)
    def write(path,data):
        path.write_bytes(data);path.chmod(0o600)
    cfg=dict(enabled=True,host="staging.example.invalid",port=22,user="media",identity_file=str(root/"key"),known_hosts=str(root/"known"),remote_inbox="/private/inbox")
    write(root/"key",b"mock");write(root/"known",b"mock")
    rows=[]
    for i in (1,2):
        name=f"task-{i}-r1-abcdefgh";directory=root/name;directory.mkdir(mode=0o700)
        png=b"\x89PNG\r\n\x1a\n"+bytes([i]);mp4=b"\x00\x00\x00\x20ftyp"+bytes([i])
        write(directory/"creative.png",png);write(directory/"creative.mp4",mp4)
        m=dict(version=1,task_id=i,revision=1,channel="threads",renderer="template",width=1080,height=1080,image_sha256=t.digest(png),video_sha256=t.digest(mp4))
        raw=json.dumps(m).encode();write(directory/"manifest.json",raw)
        rows.append(dict(task_id=i,revision=1,directory=name,manifest_sha256=t.digest(raw)))
    doc=dict(version=1,plan_sha256="a"*64,renderer="template",bundles=rows)
    def index(d):
        raw=json.dumps(d).encode();write(root/"batch-index.json",raw);return t.digest(raw)
    expected=index(doc)
    def verify(path):return t.digest((path/"manifest.json").read_bytes())
    check(len(t.reviewed_batch(root,expected,verify))==2)
    blocked(lambda:t.reviewed_batch(root,"0"*64,verify))
    for changed in ({"version":True},{"bundles":[]},{"bundles":rows*7},{"bundles":[rows[0],rows[0]]},{"renderer":"local-diffusion"},{"bundles":[{**rows[0],"directory":"../escape"}]},{"bundles":[{**rows[0],"task_id":2}]},{"bundles":[{**rows[0],"manifest_sha256":"0"*64}]}):
        bad=index({**doc,**changed});blocked(lambda:t.reviewed_batch(root,bad,verify))
    expected=index(doc)
    calls=[]
    def fake(args,**kw):calls.append(kw["input"])
    with patch.object(t.shutil,"which",return_value="/usr/bin/sftp"):
        check(t.deliver_batch(cfg,root,expected,fake,verify)==2 and len(calls)==2)
        calls.clear()
        def uncertain(args,**kw):
            calls.append(kw["input"])
            if len(calls)==2:raise OSError("Synthetic lost acknowledgement")
        try:t.deliver_batch(cfg,root,expected,uncertain,verify)
        except t.BatchInterrupted as exc:check(exc.acknowledged==1 and len(calls)==2)
        else:raise AssertionError("Interrupted batch accepted")
        calls.clear()
        # Failure in the final local bundle must prevent every network call.
        write(root/rows[1]["directory"]/"creative.mp4",b"tampered")
        blocked(lambda:t.deliver_batch(cfg,root,expected,fake,verify))
        check(not calls)
print("Reviewed SFTP batch, preflight, and interrupted acknowledgement: PASS (mock network)")
