"""Explicit one-bundle SFTP delivery. No publishing, no credential discovery."""
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

ROOT=Path(__file__).resolve().parents[1]
def private_path(path, directory=False):
    path=Path(path).expanduser()
    if path.is_symlink():
        raise ValueError("Symlink rejected")
    path=path.resolve(strict=True)
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
    m=json.loads(raw)
    keys={"version","task_id","revision","channel","renderer","width","height","image_sha256","video_sha256"}
    if not isinstance(m,dict) or set(m)!=keys or m["version"]!=1 or any(type(m[k]) is not int or m[k]<1 for k in ("task_id","revision","width","height")):
        raise ValueError("Invalid manifest")
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
    return ["-F","/dev/null","-P",str(port),"-i",str(identity),"-o","UserKnownHostsFile="+str(known),"-o","GlobalKnownHostsFile=/dev/null","-o","StrictHostKeyChecking=yes","-o","BatchMode=yes","-o","IdentitiesOnly=yes","-o","IdentityAgent=none","-o","PasswordAuthentication=no","-o","KbdInteractiveAuthentication=no","-o","ProxyCommand=none","-o","ProxyJump=none","-o","ForwardAgent=no","-o","ClearAllForwardings=yes","-o","ConnectTimeout=15","-b","-",user+"@"+host],remote

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

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--config",type=Path,required=True)
    p.add_argument("--directory",type=Path,required=True)
    p.add_argument("--manifest-sha256",required=True)
    p.add_argument("--send",action="store_true")
    a=p.parse_args()
    try:
        cfg=json.loads(read_bound(a.config,8192))
        if not a.send:
            options(cfg);bundle(a.directory,a.manifest_sha256)
            print("Local validation only; no connection made.")
        else:
            deliver(cfg,a.directory,a.manifest_sha256)
            print("SFTP batch acknowledged; server-side manifest import still required.")
        return 0
    except Exception:
        print("BLOCKED: verify private files, pinned host key and authorized inbox. Inspect partial remote files before retry.",file=sys.stderr)
        return 1
if __name__=="__main__":
    sys.exit(main())
