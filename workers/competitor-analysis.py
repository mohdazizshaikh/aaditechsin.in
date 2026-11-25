"""Offline, digest-bound editorial cues and optional local NLI review suggestions.

No network, model downloads, automatic editorial claims, or publication.
"""
import argparse
import datetime as dt
import importlib.util
import json
import os
import re
import sys
from pathlib import Path
from bs4 import BeautifulSoup

spec=importlib.util.spec_from_file_location("competitor_monitor",Path(__file__).with_name("competitor-monitor.py"))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
c=m.c;e=m.e
MAX_PAGES=12
MAX_UNITS=48
MAX_TEXT=480
RULES={
    "service_scope":(r"\b(?:service scope|scope of work|services include|solutions include|what we offer|what we cover|deliverables)\b", "The page describes the scope or deliverables of a service."),
    "process_explained":(r"\b(?:our process|how it works|our approach|site survey|assessment process|step by step|implementation process)\b", "The page explains a service assessment or delivery process."),
    "limitations_explained":(r"\b(?:limitations|exclusions|not included|subject to|depends on|prerequisites|eligibility)\b", "The page states service limitations, prerequisites, or exclusions."),
    "contact_route":(r"\b(?:contact us|request a quote|request a consultation|enquire now|inquire now|get in touch|book a consultation)\b", "The page describes a route to make a service inquiry."),
    "case_evidence_visible":(r"\b(?:case study|case studies|project portfolio|project example|customer story|completed project)\b", "The page describes case or project evidence."),
    "maintenance_options":(r"\b(?:maintenance plan|maintenance contract|annual maintenance|maintenance services|service agreement|support plan|preventive maintenance)\b", "The page describes maintenance or ongoing support options.")
}
def selection(packet,scope,today):
    c.template(packet,today)
    e.exact(scope,("version","capture_sha256","pages"))
    if type(scope["version"]) is not int or scope["version"]!=1 or scope["capture_sha256"]!=c.digest(c.canonical(packet)):raise ValueError("Exact capture")
    rows=scope["pages"]
    if type(rows) is not list or not 1<=len(rows)<=MAX_PAGES:raise ValueError("Selected pages")
    index={(s["host"],r["service_id"]):r for s in packet["subjects"] for r in s["observations"]}
    selected=[];seen=set()
    for item in rows:
        e.exact(item,("host","service_id"))
        k=(item["host"],item["service_id"])
        if k in seen or k not in index:raise ValueError("Selected page")
        seen.add(k);selected.append((k,index[k]))
    return selected
def source_name(url):
    return c.digest(url.encode("utf-8"))+".html"
def units(raw):
    c.features(raw) # Validate UTF-8 and restrictive robots metadata first.
    soup=BeautifulSoup(raw.decode("utf-8"),"html.parser")
    for tag in soup.find_all(["script","style","template","noscript","svg","iframe","object","head","nav","footer","header","aside","form"]):
        tag.decompose()
    for tag in list(soup.find_all(True)):
        if tag.name is None:continue
        attrs=tag.attrs or {}
        if "hidden" in attrs or str(attrs.get("aria-hidden","")).lower()=="true" or re.search(r"(?:display\s*:\s*none|visibility\s*:\s*hidden)",str(attrs.get("style","")),re.I):
            tag.decompose()
    main=soup.find("main") or soup.find("body")
    if main is None:raise ValueError("No bounded content region")
    out=[]
    for node in main.find_all(["h1","h2","h3","h4","p","li","dt","dd"]):
        if node.find(["h1","h2","h3","h4","p","li","dt","dd"]):continue
        text=" ".join(node.get_text(" ",strip=True).split())
        if not text:continue
        # Never emit full text or pass contact-like units to the optional model.
        if re.search(r"[\w.+-]+@[\w.-]+|\+?\d[\d ()-]{7,}\d|https?://",text,re.I):continue
        for offset in range(0,min(len(text),MAX_TEXT*2),MAX_TEXT):
            chunk=text[offset:offset+MAX_TEXT]
            if chunk:out.append(chunk)
            if len(out)>=MAX_UNITS:return out
    return out
def analyze(packet,scope,root,today,score=None):
    selected=selection(packet,scope,today);root=m.private_root(root)
    results=[]
    # Verify every selected file before invoking the optional local classifier.
    inputs=[]
    for (host,service),r in selected:
        raw=m.read_private(root/source_name(r["source_url"]),c.MAX_BODY)
        if c.digest(raw)!=r["body_sha256"] or c.features(raw)!=r["features"]:raise ValueError("Source bytes changed")
        inputs.append((host,service,r,units(raw)))
    for host,service,r,chunks in inputs:
        suggestions={}
        for signal,(pattern,hypothesis) in RULES.items():
            matches=[]
            for i,text in enumerate(chunks):
                if not re.search(pattern,text,re.I):continue
                match={"unit":i+1,"unit_sha256":c.digest(text.encode()),"cue":"lexical_match"}
                if score is not None:
                    value=score(text,hypothesis)
                    if type(value) is not int or not 0<=value<=10000:raise ValueError("Invalid local score")
                    match["entailment_basis_points"]=value
                matches.append(match)
                if len(matches)==3:break
            suggestions[signal]={"state":"review_suggested" if matches else "unknown","matches":matches}
        results.append({"host":host,"service_id":service,"source_url":r["source_url"],"body_sha256":r["body_sha256"],"units_scanned":len(chunks),"suggestions":suggestions})
    return {"version":1,"scope":"private-unreviewed-editorial-analysis","capture_sha256":scope["capture_sha256"],"selection_sha256":c.digest(c.canonical(scope)),"method":"lexical-local-nli" if score is not None else "lexical-only","pages":results,"publication_authorized":False}
def local_nli(path):
    # Owner-installed, trusted, local model only. No custom model code or pickle loading.
    p=Path(path)
    if not p.is_absolute() or p.resolve(strict=True)!=p or not p.is_dir():raise ValueError("Canonical model directory required")
    os.environ["HF_HUB_OFFLINE"]="1";os.environ["TRANSFORMERS_OFFLINE"]="1"
    import torch
    from transformers import AutoTokenizer,AutoModelForSequenceClassification
    tokenizer=AutoTokenizer.from_pretrained(str(p),local_files_only=True,trust_remote_code=False)
    model=AutoModelForSequenceClassification.from_pretrained(str(p),local_files_only=True,trust_remote_code=False,use_safetensors=True)
    labels={str(v).lower():int(k) for k,v in model.config.id2label.items()}
    if set(labels)!={"entailment","neutral","contradiction"} or set(labels.values())!={0,1,2}:raise ValueError("Explicit three-way NLI model required")
    model.to("cpu");model.eval()
    def score(text,hypothesis):
        encoded=tokenizer(text,hypothesis,return_tensors="pt",truncation=True,max_length=256)
        with torch.inference_mode():prob=torch.softmax(model(**encoded).logits[0],dim=0)[labels["entailment"]].item()
        return round(prob*10000)
    return score
def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--capture",type=Path,required=True);p.add_argument("--source-root",type=Path,required=True);p.add_argument("--model",type=Path)
    a=p.parse_args()
    try:
        scope=e.decode(sys.stdin.buffer.read(e.LIMIT+1));packet=e.decode(m.read_private(a.capture))
        today=c.utc_day();selection(packet,scope,today)
        result=analyze(packet,scope,a.source_root,today,local_nli(a.model) if a.model else None)
        if c.utc_day()!=today:raise ValueError("Review day changed")
        print(json.dumps(result,sort_keys=True,separators=(",",":")))
    except Exception:p.exit(1,"BLOCKED: exact capture, private source, local model or analysis validation failed.\n")
if __name__=="__main__":main()
