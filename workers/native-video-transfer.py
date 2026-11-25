"""Reviewed native video delivery to a private SFTP inbox; no publication."""
import argparse
import hashlib
import importlib.util
import json
import os
from datetime import date, datetime, timezone
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import tempfile

def load(name, filename):
    spec=importlib.util.spec_from_file_location(name,Path(__file__).with_name(filename))
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module
native=load("native_candidate","native-video-candidate.py")
transfer=load("media_transfer","media-transfer.py")

def read_packet(path,expected):
    packet,h=native.private_json(Path(path).expanduser().absolute())
    if not native.exact_hash(expected) or h!=expected or not isinstance(packet,dict):
        raise ValueError("Reviewed packet digest")
    day=packet.get("review_day_utc")
    if not isinstance(day,str):raise ValueError("Packet date")
    d=date.fromisoformat(day)
    if d.isoformat()!=day or d>datetime.now(timezone.utc).date():raise ValueError("Packet date")
    return packet

def destination(cfg):
    transfer.options(cfg)
    # Bind the exact configured key and host-key inventory, never return their bytes.
    key=native.private_asset(transfer.private_path(cfg["identity_file"]),65536)
    known=native.private_asset(transfer.private_path(cfg["known_hosts"]),1048576)
    return native.digest({"scope":"native-sftp-destination-v1","config":cfg,"identity_sha256":key,"known_hosts_sha256":known})

def prepare(cfg,doc,model_sha,font_sha,seed,expected,directory,index_digest,decisions,decision_digest,packet_path,packet_digest):
    dest=destination(cfg)
    packet=read_packet(packet_path,packet_digest)
    rebuilt=native.editorial_packet(doc,model_sha,font_sha,seed,expected,directory,index_digest,decisions,decision_digest,day=packet["review_day_utc"])
    if native.canonical(packet)!=native.canonical(rebuilt):raise ValueError("Packet differs from reviewed candidates")
    root=native.private_candidate_dir(directory)
    rows=[]
    for item in packet["items"]:
        r=item["review"]
        child=native.private_candidate_dir(root/f"task-{r['task_id']}-r{r['revision']}")
        assets=[]
        for filename,key,ext,limit in (("poster.png","poster_sha256","png",10*1024**2),("candidate.mp4","candidate_sha256","mp4",20*1024**2)):
            path=child/filename
            if native.private_asset(path,limit)!=r[key]:raise ValueError("Candidate changed")
            assets.append({"filename":filename,"remote_name":r[key]+"."+ext,"sha256":r[key],"bytes":path.stat().st_size})
        rows.append({"task_id":r["task_id"],"revision":r["revision"],"review_sha256":item["review_sha256"],"assets":assets})
    if destination(cfg)!=dest:raise ValueError("Destination changed")
    plan={"version":1,"scope":"native-private-sftp-v1","packet_sha256":packet_digest,"destination_sha256":dest,"rows":rows,"publication_authorized":False}
    return {"plan":plan,"plan_sha256":native.digest(plan)}

def spool(root,plan,stage):
    """Freeze every selected asset before the first connection; bounded by 12 x 30 MiB."""
    for row in plan["rows"]:
        child=native.private_candidate_dir(root/f"task-{row['task_id']}-r{row['revision']}")
        for a in row["assets"]:
            path=child/a["filename"]
            limit=10*1024**2 if a["filename"]=="poster.png" else 20*1024**2
            if native.private_asset(path,limit)!=a["sha256"]:raise ValueError("Source changed")
            data=transfer.read_bound(path,limit)
            if len(data)!=a["bytes"] or transfer.digest(data)!=a["sha256"]:raise ValueError("Source changed")
            target=stage/a["remote_name"]
            if target.exists():
                if native.private_asset(target,limit)!=a["sha256"]:raise ValueError("Spool conflict")
            else:
                fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
                with os.fdopen(fd,"wb") as f:f.write(data)

def send(cfg,prepared,reviewed_plan,directory,run=subprocess.run):
    plan=prepared["plan"]
    if not native.exact_hash(reviewed_plan) or prepared["plan_sha256"]!=reviewed_plan or native.digest(plan)!=reviewed_plan or destination(cfg)!=plan["destination_sha256"]:
        raise ValueError("Reviewed transfer plan changed")
    root=native.private_candidate_dir(directory)
    args,remote=transfer.options(cfg)
    exe=shutil.which("sftp")
    if not exe:raise ValueError("OpenSSH sftp required")
    acknowledged=0;delivered=set()
    with tempfile.TemporaryDirectory(prefix="aaditechs-native-sftp-") as tmp:
        stage=Path(tmp);spool(root,plan,stage)
        # Each pair is sent and read back; a lost acknowledgement stops the batch.
        for row in plan["rows"]:
            try:
                if destination(cfg)!=plan["destination_sha256"]:raise ValueError("Destination changed")
                lines=["cd "+remote];readbacks=[]
                for a in row["assets"]:
                    name=a["remote_name"];suffix=secrets.token_hex(16)
                    partial=".incoming-"+suffix+"-"+name;readback="readback-"+suffix
                    if name not in delivered:
                        # OpenSSH hardlink extension fails if final name exists.
                        lines.extend(["put "+name+" "+partial,"chmod 600 "+partial,"ln "+partial+" "+name,"rm "+partial])
                    lines.append("get "+name+" "+readback)
                    readbacks.append((stage/readback,a))
                run([exe,*args],input="\n".join(lines)+"\n",text=True,cwd=tmp,timeout=180,check=True,capture_output=True)
                for path,a in readbacks:
                    # SFTP may choose local file permissions from the remote; spool directory stays private.
                    path.chmod(0o600)
                    if native.private_asset(path,20*1024**2)!=a["sha256"] or path.stat().st_size!=a["bytes"]:raise ValueError("Remote readback mismatch")
                    path.unlink()
                delivered.update(a['remote_name'] for a in row['assets'])
                acknowledged+=1
            except Exception as exc:
                raise transfer.BatchInterrupted(acknowledged) from exc
    return acknowledged

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--config",type=Path,required=True)
    for arg in ("jobs","model","font","directory","decisions","packet"):p.add_argument("--"+arg,type=Path,required=True)
    for arg in ("plan-sha256","index-sha256","decision-sha256","packet-sha256"):p.add_argument("--"+arg,required=True)
    p.add_argument("--seed",type=int,required=True);p.add_argument("--send-reviewed-plan")
    a=p.parse_args()
    try:
        cfg=transfer.media.local.strict_json(transfer.read_bound(a.config,8192))
        doc=native.media.read_json(a.jobs,65536);decisions=native.media.read_json(a.decisions,65536)
        prepared=prepare(cfg,doc,native.model_inventory(a.model),native.font_digest(a.font),a.seed,a.plan_sha256,a.directory,a.index_sha256,decisions,a.decision_sha256,a.packet,a.packet_sha256)
        if a.send_reviewed_plan is None:print(json.dumps(prepared,sort_keys=True))
        else:
            count=send(cfg,prepared,a.send_reviewed_plan,a.directory)
            print(f"SFTP readback matched {count} pairs; separate server import and approval still required.")
        return 0
    except transfer.BatchInterrupted as exc:
        print(f"BLOCKED: {exc.acknowledged} pairs acknowledged before interruption. Inspect remote inbox; no automatic retry.",file=sys.stderr)
        return 1
    except Exception:
        print("BLOCKED: verify reviewed packet, private source, destination and plan. No publication.",file=sys.stderr)
        return 1
if __name__=="__main__":sys.exit(main())
