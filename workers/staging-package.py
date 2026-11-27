"""Prepare and independently verify split, exact-commit staging packages locally.

No hosting connection, extraction, uploads, configuration changes or deployment.
"""
import argparse
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tempfile
import zipfile

spec=importlib.util.spec_from_file_location("deployment_integrity",Path(__file__).with_name("deployment-integrity.py"))
d=importlib.util.module_from_spec(spec);spec.loader.exec_module(d)
NAMES=("public.zip","private-maintenance.zip")
CAP=d.MAX_TOTAL+2*1024*1024

def digest(raw):return hashlib.sha256(raw).hexdigest()
def private_dir(path):
    p=Path(path)
    if not p.is_absolute() or p.resolve(strict=True)!=p or not p.is_dir() or stat.S_IMODE(p.stat().st_mode)!=0o700:
        raise ValueError("Canonical private directory required")
    web=(d.ROOT/"public_html").resolve()
    if p==web or web in p.parents or p in web.parents:raise ValueError("Outside webroot required")
    return p
def read_private(path,cap):
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    with os.fdopen(fd,"rb") as f:
        before=os.fstat(f.fileno())
        if not stat.S_ISREG(before.st_mode) or before.st_nlink!=1 or stat.S_IMODE(before.st_mode)!=0o600 or not 0<before.st_size<=cap:raise ValueError("Private bounded file")
        data=f.read(cap+1)
        if len(data)!=before.st_size or d.identity(before)!=d.identity(os.fstat(f.fileno())):raise ValueError("Read drift")
    if d.identity(before)!=d.identity(path.lstat()):raise ValueError("Path drift")
    return data
def exclusive(path,raw):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,"wb") as f:
        f.write(raw);f.flush();os.fsync(f.fileno())
    if read_private(path,len(raw))!=raw:raise ValueError("Write drift")
def rows_for(manifest,name):
    if name not in NAMES:raise ValueError("Zone")
    prefix="public_html/" if name==NAMES[0] else "secure/maintenance/"
    return [r for r in manifest["files"] if r["path"].startswith(prefix)]
def build_archive(manifest,name,read):
    out=io.BytesIO()
    with zipfile.ZipFile(out,"w",compression=zipfile.ZIP_STORED,allowZip64=False) as z:
        for row in rows_for(manifest,name):
            raw=read(row["path"])
            if len(raw)!=row["size"] or digest(raw)!=row["sha256"]:raise ValueError("Source bytes drift")
            info=zipfile.ZipInfo(row["path"],(1980,1,1,0,0,0));info.create_system=3
            info.external_attr=(stat.S_IFREG|(0o644 if name==NAMES[0] else 0o600))<<16
            z.writestr(info,raw)
    raw=out.getvalue()
    verify_archive(raw,manifest,name)
    return raw
def verify_archive(raw,manifest,name):
    d.validate(manifest)
    if len(raw)>CAP:raise ValueError("Archive cap")
    expected=rows_for(manifest,name)
    with zipfile.ZipFile(io.BytesIO(raw),"r") as z:
        infos=z.infolist()
        if len(infos)!=len(expected) or z.comment:raise ValueError("Archive inventory")
        for info,row in zip(infos,expected):
            mode=stat.S_IFREG|(0o644 if name==NAMES[0] else 0o600)
            if info.filename!=row["path"] or info.file_size!=row["size"] or info.compress_size!=row["size"] or info.compress_type!=zipfile.ZIP_STORED or info.flag_bits!=0 or info.create_system!=3 or info.external_attr>>16!=mode or info.date_time!=(1980,1,1,0,0,0) or info.extra or info.comment:raise ValueError("Archive entry")
            with z.open(info) as f:
                h=hashlib.sha256();size=0
                while True:
                    data=f.read(65536)
                    if not data:break
                    size+=len(data)
                    if size>row["size"]:raise ValueError("Archive size")
                    h.update(data)
                if size!=row["size"] or h.hexdigest()!=row["sha256"]:raise ValueError("Archive content")
    return len(expected)
def pack(root,commit,parent):
    parent=private_dir(parent);manifest=d.source_manifest(root,commit)
    target=parent/commit
    # Exclusive output directory: interrupted preparation is never reused automatically.
    target.mkdir(mode=0o700)
    raw=d.canonical(manifest);exclusive(target/"deployment-reference.json",raw)
    def read(path):
        source="secure-setup/"+path[len("secure/"):] if path.startswith("secure/maintenance/") else path
        return subprocess.check_output(["git","-C",str(root),"show",commit+":"+source],timeout=30)
    summaries=[]
    for name in NAMES:
        archive=build_archive(manifest,name,read)
        exclusive(target/name,archive)
        summaries.append({"name":name,"sha256":digest(archive),"size":len(archive),"files":len(rows_for(manifest,name))})
    receipt={"version":1,"scope":"local-staging-package","commit":commit,"reference_sha256":digest(raw),"archives":summaries}
    receipt_raw=(json.dumps(receipt,sort_keys=True,separators=(",",":"))+"\n").encode("ascii")
    exclusive(target/"package-receipt.json",receipt_raw)
    verified=verify(target,digest(receipt_raw))
    return verified
def verify(target,expected):
    target=private_dir(target)
    if not d.sha(expected):raise ValueError("Exact reviewed receipt digest")
    if set(p.name for p in target.iterdir())!=set(NAMES)|{"deployment-reference.json","package-receipt.json"}:raise ValueError("Package inventory")
    raw=read_private(target/"package-receipt.json",65536)
    if digest(raw)!=expected:raise ValueError("Receipt digest")
    receipt=json.loads(raw.decode("ascii"))
    if raw!=(json.dumps(receipt,sort_keys=True,separators=(",",":"))+"\n").encode("ascii") or type(receipt) is not dict or set(receipt)!={"version","scope","commit","reference_sha256","archives"} or type(receipt["version"]) is not int or receipt["version"]!=1 or receipt["scope"]!="local-staging-package" or receipt["commit"]!=target.name:raise ValueError("Receipt scope")
    manifest=d.load(read_private(target/"deployment-reference.json",d.MAX_MANIFEST),receipt["reference_sha256"])
    if receipt["commit"]!=manifest["commit"] or type(receipt["archives"]) is not list or len(receipt["archives"])!=2:raise ValueError("Receipt binding")
    for entry,name in zip(receipt["archives"],NAMES):
        archive=read_private(target/name,CAP)
        count=verify_archive(archive,manifest,name)
        if entry!={"name":name,"sha256":digest(archive),"size":len(archive),"files":count}:raise ValueError("Archive receipt binding")
    return {"commit":manifest["commit"],"receipt_sha256":expected,"reference_sha256":receipt["reference_sha256"],"public_files":len(rows_for(manifest,NAMES[0])),"private_maintenance_files":len(rows_for(manifest,NAMES[1])),"local_package_verified":True,"deployment_authorized":False}

def rehearse(target,expected):
    """Reconstruct only a disposable local file tree from the verified package."""
    target=private_dir(target)
    result=verify(target,expected)
    # Re-read exact receipt and reference; bind the bytes used for reconstruction.
    receipt_raw=read_private(target/"package-receipt.json",65536)
    if digest(receipt_raw)!=expected:raise ValueError("Receipt changed")
    receipt=json.loads(receipt_raw.decode("ascii"))
    manifest=d.load(read_private(target/"deployment-reference.json",d.MAX_MANIFEST),receipt["reference_sha256"])
    if manifest["commit"]!=result["commit"]:raise ValueError("Commit changed")
    with tempfile.TemporaryDirectory(prefix="aaditechs-package-rehearsal-") as tmp:
        root=Path(tmp).resolve(strict=True)
        (root/"public_html").mkdir(mode=0o755)
        (root/"secure").mkdir(mode=0o700)
        (root/"secure/maintenance").mkdir(mode=0o700)
        for name,entry in zip(NAMES,receipt["archives"]):
            raw=read_private(target/name,CAP)
            if digest(raw)!=entry["sha256"] or len(raw)!=entry["size"]:raise ValueError("Archive changed")
            verify_archive(raw,manifest,name)
            with zipfile.ZipFile(io.BytesIO(raw),"r") as z:
                for row in rows_for(manifest,name):
                    # Use only validated manifest paths; never extract untrusted ZIP paths.
                    path=root/row["path"]
                    path.parent.mkdir(parents=True,exist_ok=True)
                    data=z.read(row["path"])
                    if len(data)!=row["size"] or digest(data)!=row["sha256"]:raise ValueError("Content changed")
                    exclusive(path,data)
                    if name==NAMES[0]:path.chmod(0o644)
        parity=d.verify(root,manifest)
        if not parity["match"]:raise ValueError("Disposable staged-copy drift")
        return result|{"disposable_copy_verified":True,"matched_files":len(parity["files"]),"unexpected_files":parity["unexpected_count"],"hosting_verified":False}

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument("mode",choices=("pack","verify","rehearse"));p.add_argument("--directory",type=Path,required=True);p.add_argument("--commit");p.add_argument("--receipt-sha256");a=p.parse_args()
    try:
        if a.mode=="pack" and a.commit and not a.receipt_sha256:r=pack(d.ROOT,a.commit,a.directory)
        elif a.mode=="verify" and a.receipt_sha256 and not a.commit:r=verify(a.directory,a.receipt_sha256)
        elif a.mode=="rehearse" and a.receipt_sha256 and not a.commit:r=rehearse(a.directory,a.receipt_sha256)
        else:raise ValueError("Exact mode")
        print(json.dumps(r,sort_keys=True))
    except Exception:p.exit(1,"BLOCKED: private output, exact source, archive inventory or reviewed receipt. Do not reuse an interrupted package.\n")
if __name__=="__main__":main()
