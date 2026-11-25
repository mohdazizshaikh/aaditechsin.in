"""Private reviewed competitor monitoring with bounded history and fail-stop scheduling."""
import argparse
import datetime as dt
import fcntl
import hashlib
import hmac
import importlib.util
import json
import os
import stat
import sys
from pathlib import Path

spec=importlib.util.spec_from_file_location("competitor_capture",Path(__file__).with_name("competitor-capture.py"))
c=importlib.util.module_from_spec(spec);spec.loader.exec_module(c)
e=c.e
LIMIT=131072
HISTORY=31
def day(value):
    if type(value) is not str:raise ValueError("UTC date")
    d=dt.date.fromisoformat(value)
    if d.isoformat()!=value:raise ValueError("UTC date")
    return d
def private_root(path):
    p=Path(path)
    if not p.is_absolute() or p.resolve(strict=True)!=p or not p.is_dir() or stat.S_IMODE(p.stat().st_mode)!=0o700:raise ValueError("Canonical private directory")
    web=(e.ROOT/"public_html").resolve()
    if p==web or p.is_relative_to(web) or web.is_relative_to(p):raise ValueError("Private directory required")
    return p
def read_private(path,cap=LIMIT):
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    with os.fdopen(fd,"rb") as f:
        a=os.fstat(f.fileno())
        if not stat.S_ISREG(a.st_mode) or stat.S_IMODE(a.st_mode)!=0o600 or a.st_nlink!=1 or not 0<a.st_size<=cap:raise ValueError("Private regular file")
        data=f.read(cap+1);b=os.fstat(f.fileno())
        if len(data)!=a.st_size or (a.st_size,a.st_mtime_ns,a.st_ctime_ns)!=(b.st_size,b.st_mtime_ns,b.st_ctime_ns):raise ValueError("File drift")
    z=os.lstat(path)
    if (z.st_dev,z.st_ino,z.st_size,z.st_mtime_ns,z.st_ctime_ns)!=(a.st_dev,a.st_ino,a.st_size,a.st_mtime_ns,a.st_ctime_ns):raise ValueError("Path drift")
    return data
def write_once(path,obj):
    raw=c.canonical(obj)+b"\n"
    if len(raw)>LIMIT:raise ValueError("Output cap")
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,"wb") as f:
        f.write(raw);f.flush();os.fsync(f.fileno())
    if read_private(path)!=raw:raise ValueError("Write verification")
def policy(doc,today):
    e.exact(doc,("version","scope","enabled","reviewed_on","expires_on","interval_days","subjects"))
    if type(doc["version"]) is not int or doc["version"]!=1 or doc["scope"]!="authorized-editorial-monitor" or doc["enabled"] is not True:raise ValueError("Monitoring disabled")
    start,end=day(doc["reviewed_on"]),day(doc["expires_on"])
    interval=doc["interval_days"]
    if not start<=today<=end or not 0<=(end-start).days<=30 or type(interval) is not int or not 1<=interval<=30:raise ValueError("Reviewed schedule bounds")
    scope={"version":1,"scope":"authorized-editorial-capture","review_day_utc":today.isoformat(),"subjects":doc["subjects"]}
    capture_plan=c.plan(scope,today)
    return scope,capture_plan
def approve(doc,key,today):
    if not isinstance(key,bytes) or len(key)!=32 or key==bytes(32):raise ValueError("Private HMAC key")
    policy(doc,today)
    return hmac.new(key,c.canonical(doc),hashlib.sha256).hexdigest()
def validate_packet(packet,expected_day,scope):
    template=c.template(packet,expected_day)
    expected=c.plan(scope,expected_day)
    if packet["plan_sha256"]!=expected["sha256"]:raise ValueError("Capture plan mismatch")
    pages=[(s["host"],r["service_id"],r["source_url"]) for s in packet["subjects"] for r in s["observations"]]
    wanted=[(s["host"],r["service_id"],r["url"]) for s in scope["subjects"] for r in s["pages"]]
    if pages!=wanted or template["capture_sha256"]!=c.digest(c.canonical(packet)):raise ValueError("Capture scope mismatch")
    return {(s["host"],r["service_id"]):r for s in packet["subjects"] for r in s["observations"]}
def compare(previous,current,doc):
    oldday,newday=day(previous["review_day_utc"]),day(current["review_day_utc"])
    if not oldday<newday or (newday-oldday).days>30:raise ValueError("Historical date range")
    oldscope,_=policy(doc,oldday);newscope,_=policy(doc,newday)
    old=validate_packet(previous,oldday,oldscope);new=validate_packet(current,newday,newscope)
    rows=[]
    for (host,service),r in new.items():
        before=old[(host,service)]
        changed=before["body_sha256"]!=r["body_sha256"]
        delta={k:r["features"][k]-before["features"][k] for k in ("heading_count","link_count")}
        if not changed and any(delta.values()):raise ValueError("Inconsistent metadata")
        rows.append({"host":host,"service_id":service,"source_url":r["source_url"],"state":"changed_review_required" if changed else "unchanged_bytes","structural_delta":delta})
    return {"version":1,"scope":"private-editorial-change-review","previous_day_utc":oldday.isoformat(),"current_day_utc":newday.isoformat(),"previous_sha256":c.digest(c.canonical(previous)),"current_sha256":c.digest(c.canonical(current)),"rows":rows,"changed_pages":sum(r["state"]=="changed_review_required" for r in rows),"publication_authorized":False}
def inventory(root,doc,key,today):
    # Only current approved policy namespace; no arbitrary paths or automatic deletion.
    tag=approve(doc,key,today)
    journal=root/tag
    if not journal.exists():return journal,[]
    if private_root(journal)!=journal:raise ValueError("Journal path")
    names=list(journal.iterdir())
    if len(names)>HISTORY*2+1:raise ValueError("History cap")
    days={}
    for p in names:
        if p.name=="lock":
            st=p.lstat()
            if not stat.S_ISREG(st.st_mode) or stat.S_IMODE(st.st_mode)!=0o600 or st.st_nlink!=1:raise ValueError("Unsafe lock")
            continue
        if not p.name.endswith((".attempt.json",".capture.json")):raise ValueError("Unknown journal entry")
        value,kind,_=p.name.split(".")
        d=day(value)
        if d>today or d<day(doc["reviewed_on"]):raise ValueError("Journal date")
        data=e.decode(read_private(p))
        if kind=="attempt":
            if data!={"version":1,"policy_hmac":tag,"day_utc":value}:raise ValueError("Attempt marker")
        else:
            scope,_=policy(doc,d);validate_packet(data,d,scope)
        days.setdefault(d,{})[kind]=data
    if len(days)>HISTORY:raise ValueError("History cap")
    for d,entry in days.items():
        if "attempt" not in entry:raise ValueError("Missing attempt marker")
        if "capture" not in entry:raise ValueError("Uncertain attempt requires review")
    return journal,sorted(days.items())
def run(doc,key,expected,root,clock=c.utc_day,capture=c.capture):
    today=clock();tag=approve(doc,key,today)
    if not c.hash_ok(expected) or not hmac.compare_digest(expected,tag):raise ValueError("Reviewed policy mismatch")
    root=private_root(root)
    journal=root/tag
    if any(p.name!=tag for p in root.iterdir()):raise ValueError("Prior policy requires private archival before change")
    try:journal.mkdir(mode=0o700)
    except FileExistsError:pass
    private_root(journal)
    fd=os.open(journal/"lock",os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW|os.O_NONBLOCK,0o600)
    try:
        st=os.fstat(fd)
        if not stat.S_ISREG(st.st_mode) or stat.S_IMODE(st.st_mode)!=0o600 or st.st_nlink!=1:raise ValueError("Private lock")
        fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        _,history=inventory(root,doc,key,today)
        if history and (today-history[-1][0]).days<doc["interval_days"]:return {"status":"not_due","publication_authorized":False}
        if len(history)>=HISTORY:raise ValueError("History cap")
        scope,plan=policy(doc,today)
        if clock()!=today:raise ValueError("Day changed")
        prefix=today.isoformat()
        write_once(journal/(prefix+".attempt.json"),{"version":1,"policy_hmac":tag,"day_utc":prefix})
        packet=capture(scope,plan["sha256"],clock=clock)
        validate_packet(packet,today,scope)
        if clock()!=today:raise ValueError("Day changed")
        report=compare(history[-1][1]["capture"],packet,doc) if history else None
        write_once(journal/(prefix+".capture.json"),packet)
        return {"status":"captured","capture_sha256":c.digest(c.canonical(packet)),"pages":sum(len(s["observations"]) for s in packet["subjects"]),"change_review":report,"publication_authorized":False}
    finally:os.close(fd)
def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument("mode",choices=("plan","run","compare"));p.add_argument("--expected");p.add_argument("--root",type=Path);a=p.parse_args()
    try:
        doc=e.decode(sys.stdin.buffer.read(LIMIT+1))
        key=bytes.fromhex(os.environ.get("AADITECHS_COMPETITOR_MONITOR_KEY_HEX",""))
        if len(key)!=32 or key==bytes(32):raise ValueError("Private key")
        today=c.utc_day();tag=approve(doc,key,today)
        if a.mode=="plan":out={"policy_hmac":tag,"expires_on":doc["expires_on"],"interval_days":doc["interval_days"],"publication_authorized":False}
        elif a.mode=="run":out=run(doc,key,a.expected,a.root)
        else:
            if not c.hash_ok(a.expected) or not hmac.compare_digest(tag,a.expected):raise ValueError("Reviewed policy")
            root=private_root(a.root)
            journal,history=inventory(root,doc,key,today)
            if len(history)<2:raise ValueError("Two completed captures required")
            out=compare(history[-2][1]["capture"],history[-1][1]["capture"],doc)
        print(json.dumps(out,sort_keys=True,separators=(",",":")))
    except Exception:p.exit(1,"BLOCKED: monitoring scope, private history, authorization or capture requires review.\n")
if __name__=="__main__":main()
