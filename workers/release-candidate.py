"""Bind a release artifact to the exact tree already validated by CI.

A same-repository pull request can package its candidate head only when the
tested synthetic merge has exactly that head's tree. Never package a different
tree or resolve a mutable branch ref. No network or deployment.
"""
import argparse
import re
import subprocess
import sys
from pathlib import Path

def resolve(root,tested,candidate,base=None):
    for value in (tested,candidate) if base is None else (tested,candidate,base):
        if not isinstance(value,str) or not re.fullmatch(r"[0-9a-f]{40}",value):
            raise ValueError("Exact commit required")
    def git(*args):
        return subprocess.check_output(["git","-C",str(root),*args],text=True,timeout=30,stderr=subprocess.DEVNULL).strip()
    if git("rev-parse","HEAD")!=tested:raise ValueError("Tested checkout mismatch")
    for value in (tested,candidate) if base is None else (tested,candidate,base):
        if git("rev-parse",value+"^{commit}")!=value:raise ValueError("Commit mismatch")
    if base is None:
        if tested!=candidate:raise ValueError("Push/manual candidate mismatch")
    else:
        # A shallow checkout can mask parents in rev-list; inspect the actual
        # commit object, and require both parent objects to be present.
        raw=git("cat-file","-p",tested).split("\n\n",1)[0].splitlines()
        parents=[line[7:] for line in raw if line.startswith("parent ")]
        if parents!=[base,candidate]:raise ValueError("Synthetic merge parents mismatch")
        if git("rev-parse",tested+"^{tree}")!=git("rev-parse",candidate+"^{tree}"):
            raise ValueError("Tested merge tree differs from release candidate")
    return candidate

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--tested",required=True);p.add_argument("--candidate",required=True);p.add_argument("--base")
    a=p.parse_args()
    try:print(resolve(Path(__file__).resolve().parents[1],a.tested,a.candidate,a.base))
    except (ValueError,OSError,subprocess.SubprocessError):p.exit(1,"BLOCKED: candidate is not the exact validated tree; review and revalidate before packaging.\n")
if __name__=="__main__":main()
