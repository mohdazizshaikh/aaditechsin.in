"""Explicit reviewed single or bounded batch SFTP delivery. No publishing or credential discovery."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import secrets
import importlib.util

spec=importlib.util.spec_from_file_location('local_media',Path(__file__).with_name('local-media.py'))
media=importlib.util.module_from_spec(spec);spec.loader.exec_module(media)

ROOT=Path(__file__).resolve().parents[1]
def private_path(path, directory=False):
    original=Path(path).expanduser().absolute()
    path=original.resolve(strict=True)
    if original!=path:
        raise ValueError("Canonical private path required")
    if path==ROOT or ROOT in path.parents or path in ROOT.parents or "public_html" in path.parts:
        raise ValueError("Private path required")
    s=path.stat()
    if (directory and not stat.S_ISDIR(s.st_mode)) or (not directory and not stat.S_ISREG(s.st_mode)) or s.st_mode&0o077 or (not directory and s.st_nlink!=1):
        raise ValueError("Private permissions required")
    return path

def read_bound(path,limit):
    path=private_path(path)
    with path.open("rb") as handle:
        data=handle.read(limit+1)
    if not data or len(data)>limit:
        raise ValueError("Size limit")
    return data

def digest(data):
    return hashlib.sha256(data).hexdigest()

def bundle(directory, expected):
    directory=private_path(directory,True)
    raw=read_bound(directory/"manifest.json",4096)
    if not re.fullmatch("[a-f0-9]{64}",expected) or digest(raw)!=expected:
        raise ValueError("Manifest mismatch")
    m=media.validate_manifest(media.local.strict_json(raw))
    result=[]
    for name,key,ext,limit in (("creative.png","image_sha256","png",10*1024*1024),("creative.mp4","video_sha256","mp4",20*1024*1024)):
        data=read_bound(directory/name,limit)
        if digest(data)!=m[key] or (ext=="png" and not data.startswith(b"\x89PNG\r\n\x1a\n")) or (ext=="mp4" and data[4:8]!=b"ftyp"):
            raise ValueError("Asset mismatch")
        result.append((m[key]+"."+ext,data))
    return result

def options(cfg):
    if not isinstance(cfg,dict) or set(cfg)!={"enabled","host","port","user","identity_file","known_hosts","remote_inbox"} or cfg["enabled"] is not True:
        raise ValueError("Disabled or invalid config")
    host,user,port,remote=cfg["host"],cfg["user"],cfg["port"],cfg["remote_inbox"]
    if not isinstance(host,str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]{0,252}",host) or not isinstance(user,str) or not re.fullmatch(r"[A-Za-z0-9_][A-Za-z0-9_-]{0,63}",user) or type(port) is not int or not 1<=port<=65535:
        raise ValueError("Invalid destination")
    if not isinstance(remote,str) or not re.fullmatch(r"/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+",remote) or any(x in (".","..","public_html","www","htdocs") for x in remote.split("/")):
        raise ValueError("Private remote inbox required")
    identity=private_path(cfg["identity_file"]);known=private_path(cfg["known_hosts"])
    if any(not re.fullmatch(r"/[A-Za-z0-9_./-]+",str(p)) for p in (identity,known)):
        raise ValueError("Unsafe SSH option path")
    return ["-F","/dev/null","-P",str(port),"-i",str(identity),"-o","UserKnownHostsFile="+str(known),"-o","GlobalKnownHostsFile=/dev/null","-o","StrictHostKeyChecking=yes","-o","UpdateHostKeys=no","-o","VerifyHostKeyDNS=no","-o","BatchMode=yes","-o","IdentitiesOnly=yes","-o","IdentityAgent=none","-o","PasswordAuthentication=no","-o","KbdInteractiveAuthentication=no","-o","ProxyCommand=none","-o","ProxyJump=none","-o","ForwardAgent=no","-o","ClearAllForwardings=yes","-o","ConnectTimeout=15","-b","-",user+"@"+host],remote

def deliver(cfg, directory, expected, run=subprocess.run):
    args,remote=options(cfg)
    assets=bundle(directory,expected)
    exe=shutil.which("sftp")
    if not exe:
        raise ValueError("OpenSSH sftp required")
    # Exact validated bytes copied to a private spool. Relative filenames contain only hashes.
    with tempfile.TemporaryDirectory(prefix="aaditechs-sftp-") as tmp:
        lines=["cd "+remote]
        for name,data in assets:
            local=Path(tmp)/name
            with local.open("xb") as handle:
                os.chmod(local,0o600)
                handle.write(data)
            partial=".incoming-"+secrets.token_hex(16)+"-"+name
            lines.extend(["put "+name+" "+partial,"rename "+partial+" "+name])
        run([exe,*args],input="\n".join(lines)+"\n",text=True,cwd=tmp,timeout=180,check=True,capture_output=True)
    return [name for name,_ in assets]

def reviewed_batch(directory,expected,verify_one=media.verify):
    directory=private_path(directory,True)
    raw=read_bound(directory/"batch-index.json",16384)
    if not isinstance(expected,str) or not re.fullmatch(r"[a-f0-9]{64}",expected) or digest(raw)!=expected:
        raise ValueError("Reviewed index changed")
    doc=media.local.strict_json(raw)
    if not isinstance(doc,dict) or set(doc)!={"version","plan_sha256","renderer","bundles"} or type(doc["version"]) is not int or doc["version"]!=1 or doc["renderer"] not in ("template","local-diffusion") or not isinstance(doc["plan_sha256"],str) or not re.fullmatch(r"[a-f0-9]{64}",doc["plan_sha256"]) or not isinstance(doc["bundles"],list) or not 1<=len(doc["bundles"])<=12:
        raise ValueError("Invalid completed index")
    seen=set();names=set();rows=[]
    for row in doc["bundles"]:
        if not isinstance(row,dict) or set(row)!={"task_id","revision","directory","manifest_sha256"} or any(type(row[k]) is not int or row[k]<1 for k in ("task_id","revision")):
            raise ValueError("Invalid bundle entry")
        name=row["directory"];hash_=row["manifest_sha256"]
        if not isinstance(name,str) or not re.fullmatch(r"task-[1-9][0-9]*-r[1-9][0-9]*-[a-z0-9_]{8}",name) or not name.startswith(f"task-{row['task_id']}-r{row['revision']}-") or not isinstance(hash_,str) or not re.fullmatch(r"[a-f0-9]{64}",hash_) or row["task_id"] in seen or name in names:
            raise ValueError("Invalid or duplicate bundle scope")
        seen.add(row["task_id"]);names.add(name)
        target=private_path(directory/name,True)
        raw_manifest=read_bound(target/"manifest.json",4096)
        manifest=media.validate_manifest(media.local.strict_json(raw_manifest))
        if digest(raw_manifest)!=hash_ or manifest["task_id"]!=row["task_id"] or manifest["revision"]!=row["revision"] or manifest["renderer"]!=doc["renderer"]:
            raise ValueError("Bundle scope mismatch")
        # Validate exact bytes and full codec integrity before any connection.
        bundle(target,hash_)
        if verify_one(target)!=hash_:
            raise ValueError("Bundle verification mismatch")
        rows.append((target,hash_))
    return rows

class BatchInterrupted(RuntimeError):
    def __init__(self,acknowledged):
        super().__init__("Batch interrupted; inspect remote inbox before retry")
        self.acknowledged=acknowledged

def deliver_batch(cfg,directory,expected,run=subprocess.run,verify_one=media.verify):
    options(cfg)
    rows=reviewed_batch(directory,expected,verify_one)
    acknowledged=0
    for target,hash_ in rows:
        try:
            # Existing one-bundle sender revalidates exact bytes and spools privately.
            deliver(cfg,target,hash_,run)
            acknowledged+=1
        except Exception as exc:
            raise BatchInterrupted(acknowledged) from exc
    return acknowledged

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--config",type=Path,required=True)
    scope=p.add_mutually_exclusive_group(required=True)
    scope.add_argument("--directory",type=Path)
    scope.add_argument("--batch-directory",type=Path)
    p.add_argument("--manifest-sha256")
    p.add_argument("--batch-index-sha256")
    p.add_argument("--send",action="store_true")
    a=p.parse_args()
    try:
        cfg=media.local.strict_json(read_bound(a.config,8192))
        if a.directory is not None:
            if not a.manifest_sha256 or a.batch_index_sha256 is not None:
                raise ValueError("Explicit manifest digest required")
            if not a.send:
                options(cfg);bundle(a.directory,a.manifest_sha256)
                print("Local validation only; no connection made.")
            else:
                deliver(cfg,a.directory,a.manifest_sha256)
                print("SFTP bundle acknowledged; server-side manifest import still required.")
        else:
            if not a.batch_index_sha256 or a.manifest_sha256 is not None:
                raise ValueError("Explicit reviewed index digest required")
            if not a.send:
                options(cfg);rows=reviewed_batch(a.batch_directory,a.batch_index_sha256)
                print(f"Local validation only: {len(rows)} bundles; no connection made.")
            else:
                count=deliver_batch(cfg,a.batch_directory,a.batch_index_sha256)
                print(f"SFTP acknowledged {count} bundles; individual server-side manifest imports still required.")
        return 0
    except BatchInterrupted as exc:
        print(f"BLOCKED: {exc.acknowledged} bundles acknowledged before interruption. Inspect remote files; no automatic retry.",file=sys.stderr)
        return 1
    except Exception:
        print("BLOCKED: verify private files, pinned host key and authorized inbox. Inspect partial remote files before retry.",file=sys.stderr)
        return 1
if __name__=="__main__":
    sys.exit(main())
