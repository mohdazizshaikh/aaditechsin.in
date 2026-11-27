import copy
import datetime as dt
import importlib.util
import tempfile
from pathlib import Path

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("monitor",root/"workers/competitor-monitor.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
today=dt.date(2026,11,26);later=today+dt.timedelta(days=2)
key=b"k"*32;ids=m.e.services();robots=b"User-agent: *\nDisallow: /admin/\n"
def doc():
    return {"version":1,"scope":"authorized-editorial-monitor","enabled":True,"reviewed_on":today.isoformat(),"expires_on":(today+dt.timedelta(days=30)).isoformat(),"interval_days":2,"subjects":[{"host":h,"authorized":True,"robots_sha256":m.c.digest(robots),"pages":[{"service_id":s,"url":"https://"+h+"/"+s+"/"} for s in ids]} for h in [m.e.OWN]+["peer"+str(i)+".example" for i in range(5)]]}
def blocked(fn):
    try:fn()
    except (ValueError,OSError):return
    raise AssertionError("Expected refusal")
d=doc();tag=m.approve(d,key,today);calls=[]
def fixture(scope,expected,clock):
    calls.append(clock())
    return m.c.capture(scope,expected,lambda h,ip,p,cap:("text/plain",robots) if p=="/robots.txt" else ("text/html",b"<h1>Fixture</h1>"),lambda h:"8.8.8.8",lambda:None,clock)
scope,p=m.policy(d,today);first=fixture(scope,p["sha256"],lambda:today)
scope,p=m.policy(d,later);second=fixture(scope,p["sha256"],lambda:later)
assert len(m.compare(first,second,d)["rows"])==84
cases=0
for i in range(6):
    for j in range(14):
        changed=copy.deepcopy(second);r=changed["subjects"][i]["observations"][j]
        r["body_sha256"]="a"*64;r["features"]["heading_count"]+=1
        out=m.compare(first,changed,d)
        assert out["changed_pages"]==1 and out["rows"][i*14+j]["structural_delta"]["heading_count"]==1
        cases+=1
        bad=copy.deepcopy(second);bad["subjects"][i]["observations"][j]["features"]["heading_count"]+=1
        blocked(lambda:m.compare(first,bad,d));cases+=1
assert cases==168
for patch in ({"enabled":False},{"interval_days":0},{"interval_days":31},{"interval_days":True},{"expires_on":(today+dt.timedelta(days=31)).isoformat()},{"reviewed_on":later.isoformat()}):
    bad={**d,**patch};blocked(lambda:m.approve(bad,key,today))
calls.clear()
with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp)
    blocked(lambda:m.run(d,key,"0"*64,p,lambda:today,fixture));assert not calls
    a=m.run(d,key,tag,p,lambda:today,fixture)
    assert a["status"]=="captured" and a["pages"]==84 and a["change_review"] is None
    assert m.run(d,key,tag,p,lambda:today,fixture)["status"]=="not_due"
    assert m.run(d,key,tag,p,lambda:today+dt.timedelta(days=1),fixture)["status"]=="not_due"
    assert calls==[today]
    b=m.run(d,key,tag,p,lambda:later,fixture)
    assert b["change_review"]["changed_pages"]==0 and calls==[today,later]
    journal,history=m.inventory(p,d,key,later);assert len(history)==2
    assert all(x.stat().st_mode&0o777==0o600 for x in journal.iterdir())
    changed={**d,"interval_days":3}
    blocked(lambda:m.run(changed,key,m.approve(changed,key,later),p,lambda:later,fixture))
    bad_day=later+dt.timedelta(days=2)
    blocked(lambda:m.run(d,key,tag,p,lambda:bad_day,lambda *args,**kw:(_ for _ in ()).throw(ValueError("fixture interruption"))))
    blocked(lambda:m.run(d,key,tag,p,lambda:bad_day,fixture));assert calls==[today,later]
    assert (journal/(bad_day.isoformat()+".attempt.json")).exists()
    assert not (journal/(bad_day.isoformat()+".capture.json")).exists()
    blocked(lambda:m.run(d,key,tag,p,lambda:today+dt.timedelta(days=31),fixture))
print("Competitor monitoring: 168 historical page/change cases, 84-page capture, due/expiry and fail-stop journal fixtures PASS")
