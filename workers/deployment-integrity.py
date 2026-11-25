"""Read-only release file inventory and isolated deployed-copy verification."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys

MAX_FILES = 1000
MAX_FILE = 32 * 1024**2
MAX_TOTAL = 256 * 1024**2
MAX_MANIFEST = 262144
ROOT = Path(__file__).resolve().parents[1]

def sha(value, size=64):
    return isinstance(value, str) and re.fullmatch(r"[a-f0-9]{%d}" % size, value) is not None

def destination(source):
    if not isinstance(source,str) or len(source)>240 or not re.fullmatch(r"[a-zA-Z0-9._/-]+",source):
        raise ValueError("Invalid path")
    parts=source.split("/")
    if any(p in ("",".","..") for p in parts):
        raise ValueError("Invalid path components")
    if source.startswith("public_html/"):
        if any(p.startswith(".") and p!=".htaccess" for p in parts) or any(p.lower().endswith((".sql",".zip",".gz",".bak",".env",".log",".pem",".key")) for p in parts):
            raise ValueError("Unsafe public entry")
        if any(p.lower() in {"secure","secure-setup","vendor","aaditechs-config.php"} for p in parts):
            raise ValueError("Private public entry")
        return source
    if re.fullmatch(r"secure-setup/maintenance/[a-z0-9-]+\.(?:php|json)",source):
        return "secure/maintenance/"+parts[-1]
    raise ValueError("Outside deployment scope")

def source_manifest(root, commit):
    if not sha(commit,40):
        raise ValueError("Exact commit required")
    root=Path(root).resolve(strict=True)
    def git(*args):
        return subprocess.check_output(["git","-C",str(root),*args],timeout=30)
    if git("rev-parse",commit+"^{commit}").decode().strip()!=commit:
        raise ValueError("Commit mismatch")
    raw=git("ls-tree","-rz","--full-tree",commit,"--","public_html","secure-setup/maintenance")
    if len(raw)>MAX_MANIFEST:
        raise ValueError("Tree cap")
    entries=[];total=0
    for line in raw.split(b"\0"):
        if not line:continue
        info,name=line.split(b"\t",1)
        mode,kind,blob=info.decode("ascii").split(" ")
        source=name.decode("ascii")
        target=destination(source)
        if mode not in ("100644","100755") or kind!="blob" or not sha(blob,40) or len(entries)>=MAX_FILES:
            raise ValueError("Unsupported tree entry")
        size=int(git("cat-file","-s",blob))
        total+=size
        if not 0<size<=MAX_FILE or total>MAX_TOTAL:
            raise ValueError("File size cap")
        data=git("cat-file","blob",blob)
        if len(data)!=size or hashlib.sha1(b"blob "+str(size).encode()+b"\0"+data).hexdigest()!=blob:
            raise ValueError("Git blob mismatch")
        entries.append({"path":target,"size":size,"sha256":hashlib.sha256(data).hexdigest()})
    entries.sort(key=lambda e:e["path"])
    result={"version":1,"scope":"aaditechs-deployed-files","commit":commit,"files":entries}
    validate(result)
    return result

def validate(doc):
    if type(doc) is not dict or list(doc)!=["version","scope","commit","files"] or type(doc["version"]) is not int or doc["version"]!=1 or doc["scope"]!="aaditechs-deployed-files" or not sha(doc["commit"],40):
        raise ValueError("Manifest scope")
    rows=doc["files"]
    if type(rows) is not list or not 1<=len(rows)<=MAX_FILES:
        raise ValueError("Manifest count")
    names=[];total=0
    for r in rows:
        if type(r) is not dict or list(r)!=["path","size","sha256"] or type(r["path"]) is not str:
            raise ValueError("Manifest entry")
        p=r["path"]
        source="secure-setup/"+p[len("secure/"):] if p.startswith("secure/maintenance/") else p
        if destination(source)!=p or type(r["size"]) is not int or not 0<r["size"]<=MAX_FILE or not sha(r["sha256"]):
            raise ValueError("Manifest entry")
        names.append(p);total+=r["size"]
    if names!=sorted(set(names)) or total>MAX_TOTAL or not any(p.startswith("public_html/") for p in names) or not any(p.startswith("secure/maintenance/") for p in names):
        raise ValueError("Manifest inventory")
    return doc

def canonical(doc):
    raw=(json.dumps(doc,ensure_ascii=True,separators=(",",":"))+"\n").encode("ascii")
    if len(raw)>MAX_MANIFEST:raise ValueError("Manifest cap")
    return raw

def load(raw,digest):
    if len(raw)>MAX_MANIFEST or not sha(digest) or hashlib.sha256(raw).hexdigest()!=digest:
        raise ValueError("Manifest digest")
    doc=validate(json.loads(raw.decode("ascii")))
    if canonical(doc)!=raw:raise ValueError("Noncanonical manifest")
    return doc

def identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)

def verify_file(root,row):
    p=root/row["path"]
    try:
        if p.resolve(strict=True)!=p:return "unsafe"
        s=p.lstat()
        if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or s.st_mode&0o022:return "unsafe"
        if row["path"].startswith("secure/") and s.st_mode&0o077:return "unsafe"
        if s.st_size!=row["size"]:return "drift"
        fd=os.open(p,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
        with os.fdopen(fd,"rb") as f:
            if identity(os.fstat(f.fileno()))!=identity(s):return "unsafe"
            h=hashlib.sha256();n=0
            for chunk in iter(lambda:f.read(65536),b""):
                n+=len(chunk)
                if n>row["size"]:return "drift"
                h.update(chunk)
            if identity(os.fstat(f.fileno()))!=identity(s) or identity(p.lstat())!=identity(s):return "unsafe"
            return "match" if n==row["size"] and h.hexdigest()==row["sha256"] else "drift"
    except FileNotFoundError:return "missing"
    except (OSError,RuntimeError):return "unsafe"

def scan(root,zone):
    base=root/zone
    if base.resolve(strict=True)!=base or not base.is_dir():raise ValueError("Unsafe zone")
    if zone.startswith("secure/") and (base.stat().st_mode&0o077 or (root/"secure").stat().st_mode&0o077):
        raise ValueError("Private directory required")
    out=set();seen=0
    for directory, dirs, files in os.walk(base,followlinks=False,onerror=lambda e: (_ for _ in ()).throw(e)):
        for name in dirs+files:
            seen+=1
            if seen>MAX_FILES*2:raise ValueError("Inventory cap")
            p=Path(directory)/name
            if p.is_symlink():raise ValueError("Symlink inventory")
            if name in files:
                out.add(p.relative_to(root).as_posix())
            elif zone=="secure/maintenance":raise ValueError("Unexpected maintenance directory")
    return out

def verify(root,doc):
    validate(doc)
    root=Path(root)
    if not root.is_absolute() or root.resolve(strict=True)!=root or not root.is_dir():
        raise ValueError("Canonical isolated root required")
    expected={r["path"] for r in doc["files"]}
    observed=scan(root,"public_html")|scan(root,"secure/maintenance")
    results={r["path"]:verify_file(root,r) for r in doc["files"]}
    # Do not output unexpected filenames: they might contain private data.
    extra=len(observed-expected)
    return {"commit":doc["commit"],"files":results,"unexpected_count":extra,
            "match":extra==0 and all(x=="match" for x in results.values())}

def main():
    p=argparse.ArgumentParser(description=__doc__)
    modes=p.add_mutually_exclusive_group(required=True)
    modes.add_argument("--capture",metavar="COMMIT")
    modes.add_argument("--verify-isolated",type=Path,metavar="DOMAIN_ROOT")
    p.add_argument("--reference-sha256")
    a=p.parse_args()
    try:
        if a.capture:
            if a.reference_sha256 is not None:raise ValueError("Unexpected reference")
            sys.stdout.buffer.write(canonical(source_manifest(ROOT,a.capture)))
            return 0
        doc=load(sys.stdin.buffer.read(MAX_MANIFEST+1),a.reference_sha256)
        result=verify(a.verify_isolated,doc)
        print(json.dumps(result,indent=2))
        return 0 if result["match"] else 1
    except (ValueError,OSError,RuntimeError,subprocess.SubprocessError):
        print("BLOCKED: deployment inventory, reference, path or file integrity.",file=sys.stderr)
        return 1

if __name__=="__main__":sys.exit(main())
