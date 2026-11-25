import copy
import datetime as dt
import importlib.util
from pathlib import Path
import socket

root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("capture",root/"workers/competitor-capture.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
today=dt.date(2026,11,26);clock=lambda:today
ids=m.e.services()
robots=b"User-agent: *\nDisallow: /admin/\n"
html=b"<html><head><title>Private fixture</title></head><body><h1>Fixture</h1><a href='/contact/'>Contact</a></body></html>"
def doc():
    return {"version":1,"scope":"authorized-editorial-capture","review_day_utc":today.isoformat(),"subjects":[{"host":h,"authorized":True,"robots_sha256":m.digest(robots),"pages":[{"service_id":s,"url":"https://"+h+"/"+s+"/"} for s in ids]} for h in (m.e.OWN,"peer.example")]}
def blocked(fn):
    try:fn()
    except ValueError:return
    raise AssertionError("Expected refusal")
calls=[]
def read(host,ip,path,cap):
    calls.append((host,ip,path,cap))
    return ("text/plain",robots) if path=="/robots.txt" else ("text/html",html)
def run(d,expected=None,reader=read,resolver=lambda _: "8.8.8.8"):
    return m.capture(d,expected or m.plan(d,today)["sha256"],reader,resolver,lambda:None,clock)
d=doc();p=m.plan(d,today)
blocked(lambda:run(d,"0"*64));assert not calls
for i,s in enumerate(ids):
    for h in range(2):
        for url in ("http://peer.example/","https://evil.example/","https://peer.example/?x=1","https://peer.example/admin/","https://peer.example/robots.txt"):
            bad=copy.deepcopy(d);bad["subjects"][h]["pages"][i]["url"]=url
            blocked(lambda:m.plan(bad,today))
packet=run(d)
assert len(calls)==30 and all(c[2]=="/robots.txt" for c in calls[:2])
assert len(packet["subjects"][1]["observations"])==14
assert all(x["signals"]==dict.fromkeys(m.e.SIGNALS,"unknown") for s in packet["subjects"] for x in s["observations"])
assert "Private fixture" not in m.canonical(packet).decode()
expected=m.digest(m.canonical(packet))
decisions={"version":1,"capture_sha256":expected,"subjects":[{"host":s["host"],"observations":[{k:v for k,v in r.items() if k in ("service_id","source_url","observed_on","signals")} for r in s["observations"]]} for s in packet["subjects"]]}
assert m.template(packet,today)==decisions
assert m.e.task_scope(m.review(packet,expected,decisions,today),today)["items"]==[]
cases=0
for i,s in enumerate(ids):
    for signal in m.e.SIGNALS:
        decision=copy.deepcopy(decisions)
        decision["subjects"][0]["observations"][i]["signals"][signal]="absent"
        decision["subjects"][1]["observations"][i]["signals"][signal]="present"
        assert m.e.task_scope(m.review(packet,expected,decision,today),today)["items"]==[{"service_id":s,"signal":signal}]
        cases+=1
assert cases==84
for h in range(2):
    for i in range(14):
        bad=copy.deepcopy(decisions);bad["subjects"][h]["observations"][i]["source_url"]="https://"+bad["subjects"][h]["host"]+"/different/"
        blocked(lambda:m.review(packet,expected,bad,today))
        bad=copy.deepcopy(decisions);bad["subjects"][h]["observations"].pop(i)
        blocked(lambda:m.review(packet,expected,bad,today))
bad=copy.deepcopy(packet);bad["subjects"][0]["observations"][0]["body_sha256"]="a"*64
blocked(lambda:m.review(bad,expected,decisions,today))
for raw in (b"User-agent: *\nDisallow: /\n",b"User-agent: *\nDisallow: /*\n",b"User-agent: *\nCrawl-delay: 20\n",b"User-agent: other\nDisallow:\n"):
    changed=copy.deepcopy(d);changed["subjects"][1]["robots_sha256"]=m.digest(raw);calls.clear()
    def policy(host,ip,path,cap):
        calls.append(path);return ("text/plain",raw if host=="peer.example" else robots)
    blocked(lambda:run(changed,reader=policy));assert calls==["/robots.txt","/robots.txt"]
for ip in ("127.0.0.1","10.0.0.1","169.254.169.254","224.0.0.1","192.0.2.1"):
    blocked(lambda:m.pinned_ip("peer.example",lambda *args:[(socket.AF_INET,0,0,"",(ip,443))]))
assert m.pinned_ip("peer.example",lambda *args:[(socket.AF_INET,0,0,"",("8.8.8.8",443))])=="8.8.8.8"
blocked(lambda:m.features(b'<meta name="robots" content="noindex">'))
blocked(lambda:m.features(b"x"*(m.MAX_BODY+1)))
calls.clear()
blocked(lambda:run(d,reader=lambda host,ip,path,cap:("text/plain",b"changed")))
assert not calls
print("Competitor capture: 84 reviewed editorial cells, 140 URL refusals, 56 review drift checks and bounded robots/DNS fixtures PASS")
