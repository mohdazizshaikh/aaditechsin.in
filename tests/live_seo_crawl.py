import importlib.util
from pathlib import Path
spec = importlib.util.spec_from_file_location("live_seo", Path(__file__).resolve().parents[1] / "workers/live-seo-crawl.py")
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
seen = []
def fixture(path):
    seen.append(path)
    if path == "/robots.txt":
        return 200, "text/plain", "\n".join(m.ROBOTS).encode()
    if path == "/data-recovery/":
        return 302, "", b""
    if path == "/it-amc/":
        raise TimeoutError()
    return 200, "text/html", ('<title>Page</title><h1>Title</h1><meta name="description" content="Text"><link rel="canonical" href="https://aaditechs.in'+path+'">').encode()
r = m.crawl(fixture, lambda: None)
assert seen == ["/robots.txt", *m.PATHS]
assert r["counts"]["html_ok"] == 5 and r["counts"]["http_non200"] == 1 and r["counts"]["unavailable"] == 1
assert sum(r["counts"][k] for k in ("missing_title","missing_description","canonical_review","noindex_meta","h1_review")) == 0
try:
    m.crawl(lambda path: (200,"text/plain",b"User-agent: *\nDisallow: /"), lambda: None)
    raise AssertionError("robots refusal required")
except ValueError:
    pass
print("Bounded public HTML fixture and robots refusal: PASS")

# Eight independent findings/status scenarios for every fixed public page.
cases = 0
base_html = lambda p: '<title>Page</title><h1>Title</h1><meta name="description" content="Text"><link rel="canonical" href="https://aaditechs.in'+p+'"><img alt="">'
for target in m.PATHS:
    for finding in ("missing_title","missing_description","h1_review","canonical_review","noindex_meta","missing_alt_page","http_non200","unavailable"):
        def read(path):
            if path == "/robots.txt":
                return 200, "text/plain", "\n".join(m.ROBOTS).encode()
            html = base_html(path)
            if path == target:
                if finding == "missing_title": html = html.replace("<title>Page</title>", "")
                elif finding == "missing_description": html = html.replace('<meta name="description" content="Text">', "")
                elif finding == "h1_review": html += "<h1>Second</h1>"
                elif finding == "canonical_review": html = html.replace("https://aaditechs.in"+path, "https://example.invalid/")
                elif finding == "noindex_meta": html += '<meta name="robots" content="noindex">'
                elif finding == "missing_alt_page": html += "<img>"
                elif finding == "http_non200": return 503, "", b""
                else: raise TimeoutError()
            return 200, "text/html", html.encode()
        report = m.crawl(read, lambda: None, page_detail=True)
        assert report["version"] == 2 and report["counts"][finding] == 1
        assert [x["path"] for x in report["page_details"]] == list(m.PATHS)
        for detail in report["page_details"]:
            if detail["path"] == target:
                if finding in ("http_non200", "unavailable"):
                    assert detail["status"] == finding and detail["findings"] == []
                else:
                    assert detail["status"] == "html_ok" and detail["findings"] == [finding]
            else:
                assert detail["status"] == "html_ok" and detail["findings"] == []
        assert "page_details" not in m.crawl(read, lambda: None)
        cases += 1
assert cases == 56
print("Page-specific SEO diagnostic matrix: 56/56 PASS")

full=m.crawl(fixture,lambda:None,all_services=True)
assert full["version"]==3 and full["pages"]==16 and full["counts"]["html_ok"]==14
assert [x["path"] for x in full["page_details"]]==list(m.FULL_PATHS)
assert len(set(m.FULL_PATHS))==16
print("Expanded 16-page live SEO scope: PASS (fixture only)")
