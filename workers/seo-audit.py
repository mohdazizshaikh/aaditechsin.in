"""Read-only SEO source audit. No network, no rewriting pages, no search metrics."""
import argparse
import json
from pathlib import Path
from urllib.parse import urlsplit, unquote, urljoin
import xml.etree.ElementTree as ET
from collections import Counter
from bs4 import BeautifulSoup

ROOT=Path(__file__).resolve().parents[1]
ORIGIN="https://aaditechs.in"
CODES={"missing_title","title_length_review","duplicate_title","missing_description","description_length_review","duplicate_description","h1_count","canonical_mismatch","missing_alt","broken_internal_link","missing_sitemap_page","unknown_sitemap_page","robots_review","noindex_public"}

def audit(web):
    web=web.resolve()
    pages={}
    findings=[]
    def flag(code,page,detail):
        findings.append({"code":code,"page":page,"detail":detail[:160]})
    for f in sorted(web.rglob("*.html")):
        if f.is_symlink() or not f.resolve().is_relative_to(web):
            continue
        path="/"+f.relative_to(web).as_posix()
        if path.endswith("index.html"):
            path=path[:-10]
        if path=="/privacy/":
            continue  # draft privacy notice deliberately not an SEO landing page
        pages[path]=BeautifulSoup(f.read_text(encoding="utf-8"),"html.parser")
    titles={}; descriptions={}
    for path,s in pages.items():
        title=s.title.get_text(" ",strip=True) if s.title else ""
        meta=s.find("meta",attrs={"name":"description"})
        desc=meta.get("content","").strip() if meta else ""
        canonical=s.select_one("link[rel=canonical]")
        if not title:flag("missing_title",path,"No title")
        elif not 25<=len(title)<=65:flag("title_length_review",path,f"{len(title)} characters; editorial heuristic")
        if not desc:flag("missing_description",path,"No meta description")
        elif not 70<=len(desc)<=170:flag("description_length_review",path,f"{len(desc)} characters; editorial heuristic")
        if title:titles.setdefault(title.casefold(),[]).append(path)
        if desc:descriptions.setdefault(desc.casefold(),[]).append(path)
        if len(s.select("h1"))!=1:flag("h1_count",path,f"{len(s.select('h1'))} H1 elements")
        if not canonical or canonical.get("href")!=ORIGIN+path:flag("canonical_mismatch",path,"Expected "+ORIGIN+path)
        for m in s.find_all("meta",attrs={"name":"robots"}):
            if "noindex" in m.get("content","").lower():flag("noindex_public",path,"Public page contains noindex")
        for img in s.select("img:not([alt])"):flag("missing_alt",path,"Image missing alt attribute")
        for a in s.select("a[href]"):
            raw=a.get("href","")
            if not raw:continue
            u=urlsplit(urljoin(ORIGIN+path,raw))
            if u.scheme!="https" or u.netloc!="aaditechs.in":continue
            target=(web/unquote(u.path).lstrip("/")).resolve()
            if target.is_dir():target=target/"index.html"
            if not target.is_relative_to(web) or not target.is_file():
                flag("broken_internal_link",path,u.path);continue
            if u.fragment and target.suffix==".html":
                fragment=unquote(u.fragment)
                ts=BeautifulSoup(target.read_text(encoding="utf-8"),"html.parser")
                if not ts.find(id=fragment) and not ts.find("a",attrs={"name":fragment}):flag("broken_internal_link",path,u.path+"#"+fragment)
    for index,code in ((titles,"duplicate_title"),(descriptions,"duplicate_description")):
        for group in index.values():
            if len(group)>1:
                for path in group:flag(code,path,"Shared with "+", ".join(x for x in group if x!=path))
    root=ET.parse(web/"sitemap.xml").getroot()
    locs={x.text for x in root.findall(".//{http://www.sitemaps.org/schemas/sitemap/0.9}loc")}
    for path in pages:
        if ORIGIN+path not in locs:flag("missing_sitemap_page",path,"Not in sitemap")
    for loc in sorted(x for x in locs if isinstance(x,str)):
        if loc not in {ORIGIN+p for p in pages}:flag("unknown_sitemap_page","/","Unexpected sitemap URL")
    robots=(web/"robots.txt").read_text(encoding="utf-8")
    for line in ("Disallow: /portal/","Disallow: /track/","Disallow: /api/","Sitemap: "+ORIGIN+"/sitemap.xml"):
        if line not in robots.splitlines():flag("robots_review","/","Expected directive missing: "+line)
    counts=dict(sorted(Counter(x["code"] for x in findings).items()))
    return {"version":1,"scope":"source-only","pages":len(pages),"counts":counts,"findings":findings[:100],"truncated":len(findings)>100}
def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument("--webroot",type=Path,default=ROOT/"public_html");a=p.parse_args()
    print(json.dumps(audit(a.webroot),ensure_ascii=False,indent=2))
if __name__=="__main__":main()
