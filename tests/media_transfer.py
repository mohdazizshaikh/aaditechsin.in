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
