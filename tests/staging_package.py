import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import subprocess
import tempfile
import zipfile

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("package",root/"workers/staging-package.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

def blocked(fn):
    try:fn()
    except (ValueError,OSError,RuntimeError,zipfile.BadZipFile):return
    raise AssertionError("Expected refusal")

with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp);repo=p/"repo";repo.mkdir();parent=p/"packages";parent.mkdir(mode=0o700)
    def git(*args):return subprocess.check_output(["git","-C",str(repo),*args],stderr=subprocess.DEVNULL)
    git("init");git("config","user.email","ci@example.invalid");git("config","user.name","CI synthetic")
    (repo/"public_html").mkdir();(repo/"secure-setup").mkdir();(repo/"secure-setup/maintenance").mkdir()
    for i in range(50):
        (repo/"public_html"/f"{i:02}.html").write_text(f"fixture {i}")
        (repo/"secure-setup/maintenance"/f"check-{i:02}.php").write_text(f"<?php /* fixture {i} */")
    (repo/"secure-setup/aaditechs-config.php.example").write_text("private example fixture")
    git("add",".");git("commit","-m","synthetic");commit=git("rev-parse","HEAD").decode().strip()
    (repo/"public_html/00.html").write_text("dirty working tree")
    result=m.pack(repo,commit,parent);target=parent/commit
    assert result["public_files"]==50 and result["private_maintenance_files"]==50
    assert not result["deployment_authorized"]
    assert m.verify(target,result["receipt_sha256"])==result
    rehearsal=m.rehearse(target,result["receipt_sha256"])
    assert rehearsal["matched_files"]==100 and rehearsal["unexpected_files"]==0
    assert rehearsal["disposable_copy_verified"] and not rehearsal["hosting_verified"] and not rehearsal["deployment_authorized"]
    manifest=m.d.load((target/"deployment-reference.json").read_bytes(),result["reference_sha256"])
    def read(path):
        return git("show",commit+":"+("secure-setup/"+path[len("secure/"):] if path.startswith("secure/") else path))
    cases=0
    for name in m.NAMES:
        archive=(target/name).read_bytes()
        assert m.build_archive(manifest,name,read)==archive
        rows=m.rows_for(manifest,name)
        assert len(rows)==50
        for row in rows:
            blocked(lambda:m.build_archive(manifest,name,lambda path: b"x"*row["size"] if path==row["path"] else read(path)))
            cases+=1
            # Construct a valid zip with one missing member and all remaining exact members.
            buf=io.BytesIO()
            with zipfile.ZipFile(io.BytesIO(archive)) as src,zipfile.ZipFile(buf,"w") as dst:
                for info in src.infolist():
                    if info.filename!=row["path"]:dst.writestr(info,src.read(info))
            blocked(lambda:m.verify_archive(buf.getvalue(),manifest,name));cases+=1
        with zipfile.ZipFile(io.BytesIO(archive)) as z:
            assert all(i.filename.startswith("public_html/" if name=="public.zip" else "secure/maintenance/") for i in z.infolist())
            assert all(not i.filename.endswith("config.php.example") for i in z.infolist())
        other=m.NAMES[1] if name==m.NAMES[0] else m.NAMES[0]
        blocked(lambda:m.verify_archive(archive,manifest,other))
    assert cases==200
    blocked(lambda:m.pack(repo,commit,parent))
    blocked(lambda:m.verify(target,"0"*64))
    extra=target/"extra";extra.write_text("fixture");blocked(lambda:m.verify(target,result["receipt_sha256"]));extra.unlink()
    public=target/"public.zip";raw=public.read_bytes()
    public.write_bytes(raw+b"x");blocked(lambda:m.verify(target,result["receipt_sha256"]));blocked(lambda:m.rehearse(target,result["receipt_sha256"]));public.write_bytes(raw)
    public.chmod(0o644);blocked(lambda:m.verify(target,result["receipt_sha256"]));public.chmod(0o600)
    alias=p/"alias";os.link(public,alias);blocked(lambda:m.verify(target,result["receipt_sha256"]));alias.unlink()
    public.unlink();public.symlink_to(target/"private-maintenance.zip");blocked(lambda:m.verify(target,result["receipt_sha256"]));public.unlink()
    public.write_bytes(raw);public.chmod(0o600)
    assert m.verify(target,result["receipt_sha256"])==result
print("Split staging package: 200 source drift/missing-member scenarios, exact commit, deterministic archive and private receipt checks PASS")
