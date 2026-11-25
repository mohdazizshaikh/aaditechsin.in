import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("integrity",root/"workers/deployment-integrity.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def blocked(fn):
    try:fn()
    except (ValueError,OSError,RuntimeError):return
    raise AssertionError("Expected refusal")

commit=subprocess.check_output(["git","-C",str(root),"rev-parse","HEAD"],text=True).strip()
actual=m.source_manifest(root,commit)
assert len(actual["files"])>=170
assert m.load(m.canonical(actual),hashlib.sha256(m.canonical(actual)).hexdigest())==actual

with tempfile.TemporaryDirectory() as tmp:
    w=Path(tmp)
    (w/"public_html").mkdir()
    (w/"secure").mkdir(mode=0o700)
    (w/"secure/maintenance").mkdir(mode=0o700)
    files={"public_html/"+str(i)+".html":b"fixture" for i in range(50)}
    files["secure/maintenance/check.php"]=b"<?php exit;"
    rows=[]
    for path,data in sorted(files.items()):
        p=w/path;p.write_bytes(data);p.chmod(0o600 if path.startswith("secure/") else 0o644)
        rows.append({"path":path,"size":len(data),"sha256":hashlib.sha256(data).hexdigest()})
    manifest={"version":1,"scope":"aaditechs-deployed-files","commit":commit,"files":rows}
    assert m.verify(w,manifest)["match"]
    cases=0
    for row in rows:
        p=w/row["path"];data=files[row["path"]]
        p.write_bytes(b"x"*len(data))
        r=m.verify(w,manifest);assert not r["match"] and r["files"][row["path"]]=="drift";cases+=1
        p.unlink()
        r=m.verify(w,manifest);assert not r["match"] and r["files"][row["path"]]=="missing";cases+=1
        p.write_bytes(data);p.chmod(0o600 if row["path"].startswith("secure/") else 0o644)
    assert cases==102 and m.verify(w,manifest)["match"]
    extra=w/"public_html"/"unknown";extra.write_text("synthetic")
    r=m.verify(w,manifest);assert r["unexpected_count"]==1 and not r["match"] and "unknown" not in json.dumps(r);extra.unlink()
    private=w/"secure/maintenance/check.php";private.chmod(0o644)
    assert m.verify(w,manifest)["files"]["secure/maintenance/check.php"]=="unsafe";private.chmod(0o600)
    symlink=w/"public_html"/"alias";symlink.symlink_to(private)
    blocked(lambda:m.verify(w,manifest));symlink.unlink()
    hard=w/"public_html"/"hard";os.link(private,hard)
    assert m.verify(w,manifest)["files"]["secure/maintenance/check.php"]=="unsafe";hard.unlink()
    raw=m.canonical(manifest)
    blocked(lambda:m.load(raw,"0"*64))
    duplicate=raw.replace(b'"version":1',b'"version":1,"version":1')
    blocked(lambda:m.load(duplicate,hashlib.sha256(duplicate).hexdigest()))
    for path in ("public_html/../secure/x","public_html/.env","public_html/a.sql","public_html/secure/x","secure-setup/aaditechs-config.php","public_html/aaditechs-config.php"):
        blocked(lambda:m.destination(path))
print("Deployment inventory: exact commit, 102 synthetic file drift/missing checks and privacy gates PASS")
