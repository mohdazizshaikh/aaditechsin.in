"""Transport-only fixture tests; real codec and packet verification are in native_video_candidate.py."""
import importlib.util
from pathlib import Path
import tempfile
import shutil
from unittest.mock import patch

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("native_transfer",root/"workers/native-video-transfer.py")
t=importlib.util.module_from_spec(spec);spec.loader.exec_module(t)
def blocked(fn):
    try:fn()
    except (ValueError,OSError):return
    raise AssertionError("Expected refusal")
def write(path,raw):
    path.write_bytes(raw);path.chmod(0o600)
with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp);candidate=p/"candidates";candidate.mkdir(mode=0o700)
    key=p/"key";known=p/"known";write(key,b"mock key");write(known,b"mock known")
    cfg=dict(enabled=True,host="staging.example.invalid",port=22,user="media",identity_file=str(key),known_hosts=str(known),remote_inbox="/private/inbox")
    rows=[]
    for i in range(1,13):
        child=candidate/f"task-{i}-r1";child.mkdir(mode=0o700)
        assets=[]
        for filename,ext,data in (("poster.png","png",b"poster fixture "+bytes([i])),("candidate.mp4","mp4",b"video fixture "+bytes([i]))):
            h=t.transfer.digest(data);write(child/filename,data)
            assets.append(dict(filename=filename,remote_name=h+"."+ext,sha256=h,bytes=len(data)))
        rows.append(dict(task_id=i,revision=1,review_sha256="a"*64,assets=assets))
    plan=dict(version=1,scope="native-private-sftp-v1",packet_sha256="b"*64,destination_sha256=t.destination(cfg),rows=rows,publication_authorized=False)
    def prepared():return dict(plan=plan,plan_sha256=t.native.digest(plan))
    calls=[];remote={}
    def fake(args,**kw):
        calls.append(kw["input"])
        for line in kw["input"].splitlines():
            parts=line.split();op=parts[0]
            if op=="put":remote[parts[2]]=(Path(kw["cwd"])/parts[1]).read_bytes()
            elif op=="ln":
                if parts[2] in remote:raise OSError("Already exists")
                remote[parts[2]]=remote[parts[1]]
            elif op=="rm":del remote[parts[1]]
            elif op=="get":write(Path(kw["cwd"])/parts[2],remote[parts[1]])
    with patch.object(t.shutil,"which",return_value="/usr/bin/sftp"):
        for i,row in enumerate(rows):
            for a in row["assets"]:
                path=candidate/f"task-{row['task_id']}-r1"/a["filename"];original=path.read_bytes()
                write(path,b"tampered");v=prepared()
                blocked(lambda:t.send(cfg,v,v["plan_sha256"],candidate,fake))
                assert not calls and not remote
                write(path,original)
        v=prepared();assert t.send(cfg,v,v["plan_sha256"],candidate,fake)==12
        assert len(calls)==12 and len(remote)==24 and not any(x.startswith(".incoming-") for x in remote)
        # Refuse replacement of an existing remote name and stop without advancing.
        calls.clear()
        try:t.send(cfg,v,v["plan_sha256"],candidate,fake)
        except t.transfer.BatchInterrupted as exc:assert exc.acknowledged==0 and len(calls)==1
        else:raise AssertionError("Overwrite accepted")
        remote.clear();calls.clear()
        def uncertain(args,**kw):
            if len(calls)==5:raise OSError("Synthetic lost acknowledgement")
            fake(args,**kw)
        try:t.send(cfg,v,v["plan_sha256"],candidate,uncertain)
        except t.transfer.BatchInterrupted as exc:assert exc.acknowledged==5 and len(remote)==10
        else:raise AssertionError("Interrupted batch accepted")
        # A shared asset in a reviewed batch is read back but not uploaded twice.
        remote.clear();calls.clear()
        first=rows[0]["assets"][0];second=rows[1]["assets"][0]
        write(candidate/"task-2-r1"/"poster.png",(candidate/"task-1-r1"/"poster.png").read_bytes())
        rows[1]["assets"][0]=dict(first)
        v=prepared();assert t.send(cfg,v,v["plan_sha256"],candidate,fake)==12
        assert len(remote)==23 and calls[1].count("put ")==1 and calls[1].count("get ")==2
print("Native transfer: 24 tamper positions, 12-pair batch, no overwrite, interruption and shared-asset readback PASS (mock network)")
