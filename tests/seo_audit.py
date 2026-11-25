import importlib.util,json,tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("seo",root/"workers/seo-audit.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
catalog=json.loads((root/"public_html/assets/service-registry.json").read_text())["services"]
topics=json.loads((root/"public_html/assets/seo-topics.json").read_text())["services"]
assert len(topics)==14 and set(topics)=={s["id"] for s in catalog}
r=m.audit(root/"public_html")
assert r["pages"]==16 and r["scope"]=="source-only"
with tempfile.TemporaryDirectory() as d:
    w=Path(d);(w/"index.html").write_text('<html><h1>A</h1><a href="/missing/">Missing</a><img src="x"></html>')
    (w/"sitemap.xml").write_text('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>')
    (w/"robots.txt").write_text("")
    f=m.audit(w)
    for code in ("missing_title","missing_description","canonical_mismatch","missing_alt","broken_internal_link","missing_sitemap_page","robots_review"):
        assert code in f["counts"],code
print("SEO source audit findings, fixture detection, 14-service topics: PASS")
print(json.dumps(r["counts"],sort_keys=True))
