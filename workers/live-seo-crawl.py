"""Explicitly authorized, bounded public-site SEO observation. No arbitrary URLs."""
import argparse
import datetime as dt
import http.client
import ipaddress
import json
import socket
import ssl
import time
from html.parser import HTMLParser

HOST = "aaditechs.in"
PATHS = ("/", "/video-conferencing-solutions/", "/auditorium-av-solutions/",
         "/professional-sound-systems/", "/av-project-planner/", "/data-recovery/", "/it-amc/")
ROBOTS = {"User-agent: *", "Disallow: /portal/", "Disallow: /track/",
          "Disallow: /api/", "Sitemap: https://aaditechs.in/sitemap.xml"}

def pinned_ip():
    answers = {x[4][0] for x in socket.getaddrinfo(HOST, 443, socket.AF_INET, socket.SOCK_STREAM)}
    if not answers or any(not ipaddress.ip_address(x).is_global or ipaddress.ip_address(x).is_multicast for x in answers):
        raise ValueError("Unsafe DNS")
    return sorted(answers)[0]

class PinnedHTTPS(http.client.HTTPSConnection):
    def __init__(self, ip):
        super().__init__(HOST, 443, timeout=8, context=ssl.create_default_context())
        self.ip = ip
    def connect(self):
        raw = socket.create_connection((self.ip, 443), self.timeout)
        try:
            self.sock = self._context.wrap_socket(raw, server_hostname=HOST)
        except BaseException:
            raw.close()
            raise

def fetch(ip, path):
    if path not in PATHS and path != "/robots.txt":
        raise ValueError("Path not allowed")
    conn = PinnedHTTPS(ip)
    try:
        conn.request("GET", path, headers={"User-Agent": "AaditechsOwnerSEO/1.0",
                                          "Accept": "text/html,text/plain", "Accept-Encoding": "identity",
                                          "Connection": "close"})
        r = conn.getresponse()
        if r.status != 200:
            return r.status, "", b""
        if r.getheader("Content-Encoding", "identity").lower() != "identity":
            raise ValueError("Unsupported encoding")
        body = bytearray()
        deadline = time.monotonic() + 12
        while True:
            if time.monotonic() > deadline:
                raise TimeoutError("Read deadline")
            chunk = r.read1(16384)
            if not chunk:
                break
            body.extend(chunk)
            if len(body) > 524288:
                raise ValueError("Body cap")
        return r.status, r.getheader("Content-Type", "").split(";")[0].strip().lower(), bytes(body)
    finally:
        conn.close()

class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.in_title = False
        self.title = []
        self.h1 = 0
        self.desc = []
        self.canon = []
        self.noindex = False
        self.missing_alt = False
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "title":
            self.in_title = True
        if tag == "h1":
            self.h1 += 1
        if tag == "meta" and (a.get("name") or "").lower() == "description":
            self.desc.append(a.get("content") or "")
        if tag == "meta" and (a.get("name") or "").lower() in ("robots", "googlebot"):
            self.noindex |= "noindex" in (a.get("content") or "").lower()
        if tag == "link" and "canonical" in (a.get("rel") or "").lower().split():
            self.canon.append(a.get("href"))
        if tag == "img" and "alt" not in a:
            self.missing_alt = True
    def handle_endtag(self, tag):
        if tag == "title":
            self.in_title = False
    def handle_data(self, data):
        if self.in_title:
            self.title.append(data)

def crawl(read, wait=lambda: time.sleep(1), page_detail=False):
    status, kind, raw = read("/robots.txt")
    if status != 200 or kind != "text/plain" or len(raw) > 8192:
        raise ValueError("Robots unavailable")
    rules = [x.split("#", 1)[0].strip() for x in raw.decode("utf-8").splitlines()]
    rules = [x for x in rules if x]
    # Deliberately conservative: stop if deployed policy differs from reviewed source.
    if len(rules) != len(ROBOTS) or set(rules) != ROBOTS:
        raise ValueError("Review changed robots policy")
    counts = dict.fromkeys(("html_ok", "http_non200", "unavailable", "missing_title",
                           "missing_description", "h1_review", "canonical_review",
                           "noindex_meta", "missing_alt_page"), 0)
    details = []
    for path in PATHS:
        wait()
        try:
            status, kind, raw = read(path)
            if status != 200:
                counts["http_non200"] += 1
                details.append({"path": path, "status": "http_non200", "findings": []})
                continue
            if kind != "text/html" or not raw or len(raw) > 524288:
                raise ValueError("Not bounded HTML")
            page = Page()
            page.feed(raw.decode("utf-8"))
            page.close()
            counts["html_ok"] += 1
            findings = []
            for key, condition in (
                ("missing_title", not "".join(page.title).strip()),
                ("missing_description", not any(x.strip() for x in page.desc)),
                ("h1_review", page.h1 != 1),
                ("canonical_review", page.canon != ["https://" + HOST + path]),
                ("noindex_meta", page.noindex),
                ("missing_alt_page", page.missing_alt),
            ):
                counts[key] += int(condition)
                if condition:
                    findings.append(key)
            details.append({"path": path, "status": "html_ok", "findings": findings})
        except (OSError, ValueError, UnicodeError, http.client.HTTPException):
            counts["unavailable"] += 1
            details.append({"path": path, "status": "unavailable", "findings": []})
    report = {"version": 2 if page_detail else 1, "scope": "public-html-observation", "origin": "https://" + HOST,
              "captured_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
              "pages": len(PATHS), "counts": counts}
    if page_detail:
        report["page_details"] = details
    return report

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--capture-authorized", action="store_true", required=True)
    parser.add_argument("--page-detail", action="store_true", help="Private fixed-path findings; no page contents")
    args = parser.parse_args()
    try:
        ip = pinned_ip()
        print(json.dumps(crawl(lambda path: fetch(ip, path), page_detail=args.page_detail), separators=(",", ":")))
    except Exception:
        parser.exit(1, "BLOCKED: DNS, robots or transport policy; no report produced.\n")

if __name__ == "__main__":
    main()
