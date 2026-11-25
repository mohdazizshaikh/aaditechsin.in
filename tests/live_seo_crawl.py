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
