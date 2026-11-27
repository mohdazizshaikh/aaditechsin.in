import importlib.util
from pathlib import Path
import subprocess
import tempfile

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("candidate",root/"workers/release-candidate.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def blocked(fn):
    try:fn()
    except (ValueError,OSError,subprocess.SubprocessError):return
    raise AssertionError("Expected release candidate refusal")

with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp)
    def git(*args):return subprocess.check_output(["git","-C",str(p),*args],text=True,stderr=subprocess.DEVNULL).strip()
    git("init");git("config","user.email","ci@example.invalid");git("config","user.name","Synthetic CI")
    (p/"fixture").write_text("base\n");git("add",".");git("commit","-m","base")
    base=git("rev-parse","HEAD")
    (p/"fixture").write_text("candidate\n");git("add",".");git("commit","-m","candidate")
    head=git("rev-parse","HEAD");tree=git("rev-parse","HEAD^{tree}");base_tree=git("rev-parse",base+"^{tree}")
    assert m.resolve(p,head,head)==head
    for invalid in ("HEAD","main",head[:7],head.upper(),"",None,"0"*40):
        blocked(lambda:m.resolve(p,head,invalid))
    blocked(lambda:m.resolve(p,head,base))
    blocked(lambda:m.resolve(p,base,base))
    def commit_tree(t,*parents):
        args=["commit-tree",t,"-m","synthetic merge"]
        for parent in parents:args.extend(["-p",parent])
        return git(*args)
    def checkout(commit):git("checkout","--detach",commit)
    merge=commit_tree(tree,base,head);checkout(merge)
    assert m.resolve(p,merge,head,base)==head
    blocked(lambda:m.resolve(p,merge,head))
    blocked(lambda:m.resolve(p,merge,base,head))
    blocked(lambda:m.resolve(p,merge,head,"0"*40))
    blocked(lambda:m.resolve(p,head,head,base))
    # The same two parents with a different merge tree must be rejected.
    bad=commit_tree(base_tree,base,head);checkout(bad)
    blocked(lambda:m.resolve(p,bad,head,base))
    # Wrong parent order or missing/extra parents never qualify.
    for parents in ((head,base),(head,),(base,),(),(base,head,merge)):
        bad=commit_tree(tree,*parents);checkout(bad)
        blocked(lambda:m.resolve(p,bad,head,base))
    checkout(merge)
    assert m.resolve(p,merge,head,base)==head
print("Release candidate binding: exact push and identical PR tree accepted; wrong commit, parent and divergent tree refused")
