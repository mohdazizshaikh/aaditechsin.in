"""Reviewed bounded public HTML capture for private competitor editorial review."""
import argparse
import datetime as dt
import hashlib
import http.client
import importlib.util
import ipaddress
import json
import re
import socket
import ssl
import sys
import time
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit

spec=importlib.util.spec_from_file_location("competitor_evidence",Path(__file__).with_name("competitor-evidence.py"))
e=importlib.util.module_from_spec(spec);spec.loader.exec_module(e)
UA="AaditechsEditorialReview"
MAX_BODY=524288
MAX_ROBOTS=32768
def canonical(v):
    return json.dumps(v,sort_keys=True,separators=(",",":"),ensure_ascii=True).encode()
def digest(v):
    return hashlib.sha256(v).hexdigest()
def hash_ok(v):
    return type(v) is str and re.fullmatch(r"[a-f0-9]{64}",v) is not None
def utc_day():
    return dt.datetime.now(dt.timezone.utc).date()
def plan(doc,today=None):
    today=today or utc_day()
    e.exact(doc,("version","scope","review_day_utc","subjects"))
    if type(doc["version"]) is not int or doc["version"]!=1 or doc["scope"]!="authorized-editorial-capture" or doc["review_day_utc"]!=today.isoformat():
        raise ValueError("Current reviewed scope required")
    rows=doc["subjects"]
    if type(rows) is not list or not 2<=len(rows)<=6:raise ValueError("Subject count")
    ids=e.services();seen=set();result=[]
    for item in rows:
        e.exact(item,("host","authorized","robots_sha256","pages"))
        host=e.host(item["host"])
        if host in seen or host=="www."+e.OWN or item["authorized"] is not True or not hash_ok(item["robots_sha256"]):raise ValueError("Authorization/host")
        seen.add(host);pages=item["pages"]
        if type(pages) is not list or not 1<=len(pages)<=14:raise ValueError("Page count")
        services=set();urls=set();approved=[]
        for page in pages:
            e.exact(page,("service_id","url"))
            service=page["service_id"]
            if type(service) is not str or service not in ids or service in services:raise ValueError("Service")
            url=e.evidence_url(page["url"],host)
            if url!=page["url"] or url in urls or urlsplit(url).path=="/robots.txt":raise ValueError("Exact distinct page URL")
            services.add(service);urls.add(url);approved.append({"service_id":service,"url":url})
        result.append({"host":host,"robots_sha256":item["robots_sha256"],"pages":approved})
    if e.OWN not in seen:raise ValueError("Own site required")
    p={"version":1,"scope":"private-editorial-capture-plan","review_day_utc":today.isoformat(),"subjects":result,"publication_authorized":False}
    return {"plan":p,"sha256":digest(canonical(p))}
def pinned_ip(host,resolver=socket.getaddrinfo):
    records=resolver(host,443,socket.AF_INET,socket.SOCK_STREAM)
    ips={x[4][0] for x in records}
    if not ips:raise ValueError("DNS unavailable")
    for ip in ips:
        a=ipaddress.ip_address(ip)
        if a.version!=4 or not a.is_global or a.is_multicast or a.is_reserved or a.is_loopback or a.is_link_local:raise ValueError("Nonpublic DNS")
    return sorted(ips)[0]
class PinnedHTTPS(http.client.HTTPSConnection):
    def __init__(self,host,ip):
        super().__init__(host,443,timeout=8,context=ssl.create_default_context());self.ip=ip
    def connect(self):
        raw=socket.create_connection((self.ip,443),self.timeout)
        try:self.sock=self._context.wrap_socket(raw,server_hostname=self.host)
        except BaseException:raw.close();raise
def fetch(host,ip,path,cap):
    conn=PinnedHTTPS(host,ip)
    try:
        conn.request("GET",path,headers={"User-Agent":UA+"/1.0","Accept":"text/html,text/plain","Accept-Encoding":"identity","Connection":"close"})
        r=conn.getresponse()
        if r.status!=200 or r.getheader("Content-Encoding","identity").lower()!="identity":raise ValueError("HTTP response refused")
        if r.getheader("X-Robots-Tag"):raise ValueError("Response robots restrictions require review")
        raw=bytearray();deadline=time.monotonic()+12
        while True:
            if time.monotonic()>deadline:raise TimeoutError("Read deadline")
            chunk=r.read1(16384)
            if not chunk:break
            raw.extend(chunk)
            if len(raw)>cap:raise ValueError("Body cap")
        if not raw:raise ValueError("Empty response")
        return r.getheader("Content-Type","").split(";")[0].strip().lower(),bytes(raw)
    finally:conn.close()
def robots(host,raw,expected,urls):
    if len(raw)>MAX_ROBOTS or digest(raw)!=expected:raise ValueError("Reviewed robots changed")
    lines=raw.decode("utf-8-sig").splitlines()
    # Conservative subset only: no wildcard/anchor/percent rules or unknown directives.
    # Unsupported robots policies need manual interpretation, not a permissive parser.
    groups=[];agents=[];rules=[]
    for line in lines:
        line=line.split("#",1)[0].strip()
        if not line:continue
        if ":" not in line:raise ValueError("Robots syntax")
        k,v=(x.strip() for x in line.split(":",1));k=k.lower()
        if k=="sitemap":continue
        if k=="user-agent":
            if rules:groups.append((agents,rules));agents=[];rules=[]
            if not re.fullmatch(r"[A-Za-z0-9_-]+|\*",v):raise ValueError("Robots agent")
            agents.append(v.lower())
        elif k in {"allow","disallow"}:
            if not agents or (v and not re.fullmatch(r"/[A-Za-z0-9_./~-]*",v)):raise ValueError("Unsupported robots rule")
            rules.append((k,v))
        else:raise ValueError("Unsupported robots directive")
    if agents:groups.append((agents,rules))
    if not groups:raise ValueError("Explicit robots group required")
    for url in urls:
        path=urlsplit(url).path
        # Respect wildcard AND every matching named group. Any matching disallow blocks,
        # even when an Allow might override it: conservative fail-closed.
        for agents,rules in groups:
            if not any(a=="*" or a in UA.lower() for a in agents):continue
            for k,v in rules:
                if k=="disallow" and v and path.startswith(v):raise ValueError("Robots disallows page")
    if not any(a=="*" or a in UA.lower() for agents,_ in groups for a in agents):raise ValueError("No applicable robots group")
class Features(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True);self.headings=0;self.links=0;self.restricted=False
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag in ("h1","h2","h3"):self.headings+=1
        if tag=="a" and a.get("href"):self.links+=1
        if tag=="meta" and (a.get("name") or "").lower() in ("robots","googlebot",UA.lower()):
            tokens=re.split(r"[\s,]+",(a.get("content") or "").lower())
            if any(x in tokens for x in ("noindex","nofollow","nosnippet","none","noarchive")):self.restricted=True
def features(raw):
    if not raw or len(raw)>MAX_BODY:raise ValueError("HTML cap")
    p=Features();p.feed(raw.decode("utf-8"));p.close()
    if p.restricted:raise ValueError("Page robots restriction")
    return {"heading_count":p.headings,"link_count":p.links}
def capture(doc,expected,read=fetch,resolve=pinned_ip,wait=lambda:time.sleep(2),clock=utc_day):
    today=clock();prepared=plan(doc,today)
    if not hash_ok(expected) or expected!=prepared["sha256"]:raise ValueError("Reviewed plan changed")
    p=prepared["plan"];subjects=[]
    # Check all hosts and robots policies before requesting any page.
    bindings=[]
    for subject in p["subjects"]:
        host=subject["host"];ip=resolve(host);wait()
        if clock()!=today:raise ValueError("Day changed")
        kind,raw=read(host,ip,"/robots.txt",MAX_ROBOTS)
        if kind!="text/plain":raise ValueError("Robots content type")
        robots(host,raw,subject["robots_sha256"],[x["url"] for x in subject["pages"]])
        bindings.append((subject,ip))
    for subject,ip in bindings:
        host=subject["host"];rows=[]
        for page in subject["pages"]:
            wait()
            if clock()!=today:raise ValueError("Day changed")
            kind,raw=read(host,ip,urlsplit(page["url"]).path,MAX_BODY)
            if kind!="text/html":raise ValueError("HTML required")
            rows.append({"service_id":page["service_id"],"source_url":page["url"],"observed_on":today.isoformat(),"body_sha256":digest(raw),"features":features(raw),"signals":dict.fromkeys(e.SIGNALS,"unknown")})
        subjects.append({"host":host,"observations":rows})
    if clock()!=today:raise ValueError("Day changed")
    return {"version":1,"scope":"private-unreviewed-editorial-capture","review_day_utc":today.isoformat(),"plan_sha256":expected,"subjects":subjects,"publication_authorized":False}
def review(packet,expected,decisions,today=None):
    today=today or utc_day()
    if not hash_ok(expected) or digest(canonical(packet))!=expected:raise ValueError("Exact capture digest required")
    e.exact(packet,("version","scope","review_day_utc","plan_sha256","subjects","publication_authorized"))
    if type(packet["version"]) is not int or packet["version"]!=1 or packet["scope"]!="private-unreviewed-editorial-capture" or packet["publication_authorized"] is not False or packet["review_day_utc"]!=today.isoformat() or not hash_ok(packet["plan_sha256"]):raise ValueError("Capture scope")
    e.exact(decisions,("version","capture_sha256","subjects"))
    if type(decisions["version"]) is not int or decisions["version"]!=1 or decisions["capture_sha256"]!=expected:raise ValueError("Review binding")
    evidence={"version":1,"scope":"reviewed-public-editorial-evidence","subjects":decisions["subjects"]}
    checked=e.normalize(evidence,today);seen=set()
    for subject in packet["subjects"]:
        e.exact(subject,("host","observations"));host=e.host(subject["host"])
        if host in seen or host not in checked:raise ValueError("Host scope")
        seen.add(host);services=set()
        for row in subject["observations"]:
            e.exact(row,("service_id","source_url","observed_on","body_sha256","features","signals"))
            s=row["service_id"]
            if s in services or s not in checked[host] or not hash_ok(row["body_sha256"]) or row["signals"]!=dict.fromkeys(e.SIGNALS,"unknown"):raise ValueError("Capture row")
            e.exact(row['features'],('heading_count','link_count'))
            if any(type(x) is not int or not 0<=x<=MAX_BODY for x in row['features'].values()):raise ValueError('Feature bounds')
            services.add(s);v=checked[host][s]
            if v["source_url"]!=row["source_url"] or v["observed_on"]!=row["observed_on"]:raise ValueError("Evidence source changed")
        if services!=set(checked[host]):raise ValueError("Review scope changed")
    if seen!=set(checked):raise ValueError("Review subject changed")
    return evidence
def template(packet,today=None):
    today=today or utc_day();expected=digest(canonical(packet))
    decisions={"version":1,"capture_sha256":expected,"subjects":[{"host":s["host"],"observations":[{k:r[k] for k in ("service_id","source_url","observed_on","signals")} for r in s["observations"]]} for s in packet["subjects"]]}
    review(packet,expected,decisions,today)
    return decisions

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument("mode",choices=("plan","capture","review-template","review"));p.add_argument("--expected");p.add_argument("--decisions",type=Path);a=p.parse_args()
    try:
        doc=e.decode(sys.stdin.buffer.read(e.LIMIT+1))
        if a.mode=="plan":out=plan(doc)
        elif a.mode=="capture":out=capture(doc,a.expected)
        elif a.mode=="review-template":out=template(doc)
        else:
            if a.decisions is None:raise ValueError("Reviewed decisions required")
            with a.decisions.open("rb") as f:decisions=e.decode(f.read(e.LIMIT+1))
            out=review(doc,a.expected,decisions)
        print(json.dumps(out,sort_keys=True,separators=(",",":")))
    except Exception:p.exit(1,"BLOCKED: reviewed scope, robots, DNS, response or editorial decision. No report produced.\n")
if __name__=="__main__":main()
