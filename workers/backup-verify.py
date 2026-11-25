"""Verify a private gzip database dump against an independently recorded SHA256. Never restore or execute SQL."""
import argparse
import gzip
import hashlib
import os
from pathlib import Path
import re
import stat
import sys

def verify(path, expected, max_plain=4*1024**3):
    if type(max_plain) is not int or not 1<=max_plain<=4*1024**3:
        raise ValueError("Invalid expanded size limit")
    if not re.fullmatch(r"[a-f0-9]{64}",expected):
        raise ValueError("Expected digest required")
    path=Path(path).expanduser()
    root=Path(__file__).resolve().parents[1]
    resolved=path.resolve(strict=True)
    if path.is_symlink() or root in resolved.parents or resolved==root or "public_html" in resolved.parts:
        raise ValueError("Private backup required")
    # Refuse aliases through symlinked intermediate directories as well.
    if path.absolute()!=resolved:
        raise ValueError("Use an absolute canonical private backup path")
    parent=resolved.parent.stat()
    if parent.st_mode&0o077:
        raise ValueError("Private directory required")
    fd=os.open(resolved,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    with os.fdopen(fd,"rb") as raw:
        before=os.fstat(raw.fileno())
        if not stat.S_ISREG(before.st_mode) or before.st_mode&0o077 or before.st_nlink!=1 or not 0<before.st_size<=4*1024**3:
            raise ValueError("Private bounded regular file required")
        h=hashlib.sha256();compressed=0
        for chunk in iter(lambda:raw.read(1024*1024),b""):
            compressed+=len(chunk)
            if compressed>before.st_size: raise ValueError("Backup grew while reading")
            h.update(chunk)
        if compressed!=before.st_size or h.hexdigest()!=expected: raise ValueError("Digest mismatch")
        raw.seek(0);size=0
        with gzip.GzipFile(fileobj=raw,mode="rb") as gz:
            for chunk in iter(lambda:gz.read(1024*1024),b""):
                size+=len(chunk)
                if size>max_plain: raise ValueError("Expanded size limit")
        after=os.fstat(raw.fileno())
        final=resolved.stat(follow_symlinks=False)
        if size==0 or (before.st_dev,before.st_ino,before.st_size,before.st_mtime_ns,before.st_ctime_ns)!=(after.st_dev,after.st_ino,after.st_size,after.st_mtime_ns,after.st_ctime_ns) or (after.st_dev,after.st_ino,after.st_mode,after.st_nlink)!=(final.st_dev,final.st_ino,final.st_mode,final.st_nlink):
            raise ValueError("Empty or changed backup")
        return before.st_size,size

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--file",required=True);p.add_argument("--sha256",required=True)
    a=p.parse_args()
    try:
        compressed,expanded=verify(a.file,a.sha256)
        print(f"PASS: SHA256 and gzip stream verified ({compressed} compressed bytes, {expanded} expanded bytes).")
        print("No SQL executed; restore, schema and data completeness NOT verified.")
        return 0
    except Exception:
        print("BLOCKED: private permissions, expected digest, gzip integrity or size limit.",file=sys.stderr)
        return 1
if __name__=="__main__":sys.exit(main())
