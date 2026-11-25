import copy
import datetime as dt
import importlib.util
import os
import tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("analysis",root/"workers/competitor-analysis.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
today=dt.date(2026,11,26);ids=m.e.services()
robots=b"User-agent: *\nDisallow: /admin/\n"
html=b"""<html><body><main><h1>Service scope</h1><p>Our process</p><p>Limitations and exclusions</p><p>Request a quote</p><p>Case study</p><p>Maintenance plan</p><script>private script text</script><p hidden>maintenance contract</p><p>Contact us at private@example.com</p></main></body></html>"""
doc={"version":1,"scope":"authorized-editorial-capture","review_day_utc":today.isoformat(),"subjects":[{"host":h,"authorized":True,"robots_sha256":m.c.digest(robots),"pages":[{"service_id":s,"url":"https://"+h+"/"+s+"/"} for s in ids]} for h in (m.e.OWN,"peer.example")]}
packet=m.c.capture(doc,m.c.plan(doc,today)["sha256"],lambda h,ip,path,cap:("text/plain",robots) if path=="/robots.txt" else ("text/html",html),lambda h:"8.8.8.8",lambda:None,lambda:today)
pages=[{"host":s["host"],"service_id":r["service_id"]} for s in packet["subjects"] for r in s["observations"]]
def scope(rows):return {"version":1,"capture_sha256":m.c.digest(m.c.canonical(packet)),"pages":rows}
def blocked(fn):
    try:fn()
    except (ValueError,OSError):return
    raise AssertionError("Expected refusal")
assert len(m.units(html))==6
assert not m.units(b"<html><body><nav><p>Case study</p></nav><main><p hidden>Our process</p><p aria-hidden='true'>Contact us</p><p style='display:none'>Maintenance plan</p><p>private@example.com</p></main></body></html>")
blocked(lambda:m.units(b"<html><head><meta name='robots' content='noindex'></head><body><main><p>Case study</p></main></body></html>"))
blocked(lambda:m.selection(packet,scope(pages[:13]),today))
blocked(lambda:m.selection(packet,scope([pages[0],pages[0]]),today))
blocked(lambda:m.selection(packet,scope([{"host":"other.example","service_id":ids[0]}]),today))
calls=[]
def score(text,hypothesis):
    assert len(text)<=m.MAX_TEXT and "private" not in text
    calls.append((text,hypothesis));return 8123
with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp)
    for s in packet["subjects"]:
        for r in s["observations"]:
            name=p/m.source_name(r["source_url"]);name.write_bytes(html);name.chmod(0o600)
    count=0
    for offset in range(0,len(pages),m.MAX_PAGES):
        selected=scope(pages[offset:offset+m.MAX_PAGES]);out=m.analyze(packet,selected,p,today,score)
        assert out["method"]=="lexical-local-nli" and not out["publication_authorized"]
        assert len(out["pages"])==len(selected["pages"])
        for row in out["pages"]:
            for signal in m.e.SIGNALS:
                result=row["suggestions"][signal]
                assert result["state"]=="review_suggested" and len(result["matches"])==1
                assert result["matches"][0]["entailment_basis_points"]==8123
                count+=1
        serialized=m.c.canonical(out).decode()
        for sensitive in ("private@example.com","Service scope","Our process","Case study","private script text"):
            assert sensitive not in serialized
    assert count==168 and len(calls)==168
    one=scope(pages[:2]);out=m.analyze(packet,one,p,today)
    assert out["method"]=="lexical-only"
    assert all("entailment_basis_points" not in x for r in out["pages"] for v in r["suggestions"].values() for x in v["matches"])
    first=packet["subjects"][0]["observations"][0]
    name=p/m.source_name(first["source_url"])
    before=len(calls)
    name.write_bytes(html+b" ");blocked(lambda:m.analyze(packet,one,p,today,score));assert len(calls)==before
    name.write_bytes(html);name.chmod(0o644);blocked(lambda:m.analyze(packet,one,p,today,score));name.chmod(0o600)
    name.unlink();name.symlink_to(p/m.source_name(packet["subjects"][0]["observations"][1]["source_url"]))
    blocked(lambda:m.analyze(packet,one,p,today,score));name.unlink();name.write_bytes(html);name.chmod(0o600)
    blocked(lambda:m.analyze(packet,one,p,today,lambda *a:10001))
    blocked(lambda:m.analyze(packet,one,p,today,lambda *a:True))
    empty=b"<html><body><main><p>Unrelated text.</p></main></body></html>"
    revised=copy.deepcopy(packet);r=revised["subjects"][0]["observations"][0];r["body_sha256"]=m.c.digest(empty);r["features"]=m.c.features(empty)
    name.write_bytes(empty);chosen={"version":1,"capture_sha256":m.c.digest(m.c.canonical(revised)),"pages":pages[:1]}
    report=m.analyze(revised,chosen,p,today)
    assert all(v["state"]=="unknown" and v["matches"]==[] for v in report["pages"][0]["suggestions"].values())
print("Competitor analysis: 168 service/host/signal cue cases, local scorer fixture, source tamper, hidden text, privacy and unknown evidence checks PASS")
