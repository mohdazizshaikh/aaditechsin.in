# V10.0 SEO expert — read-only source phase

Run locally with Python 3.11+ and BeautifulSoup 4.12.3: python3 workers/seo-audit.py > seo-report.json. The worker inspects checked-out static public landing pages (privacy draft excluded), canonical, title/description, H1, missing image alt attributes, same-origin links and fragments, sitemap membership and expected robots directives. Title and description length ranges are editorial heuristics, not ranking rules. No crawling remote websites, automatic modifications, live ranking claims, keyword volumes, Google Search Console queries or competitor scraping. JS-generated content and actual HTTP/indexing are not audited.

The public topic registry contains 3 suggested editorial phrases for each of 14 services, including Mumbai variants. These are hypotheses, not researched volume/competition data. Review service availability and location claims before writing.

Optional private reporting: after backed-up staging migration V10.0, set seo.enabled true with growth enabled and resumed. Transfer the local report privately to the server and run php secure/maintenance/seo-worker.php --register < seo-report.json. Only allowlisted counts and page count are retained, with identical summary deduplication (not a run log). Detailed findings remain in the local JSON. /seo returns last distinct summary. /seotopics SERVICE returns topic suggestions. Review and propose website changes in a separate PR. No automatic edits. Default OFF.

Pending: authorized Search Console property and read-only OAuth, live HTTPS crawl and index status, verified local business information, competitor analysis, keyword evidence, analytics attribution, periodic audit scheduling and approved editorial change workflow. Never claim ranking or traffic results from source-only tests.


## V10.7A — competitor editorial evidence (source only)
Offline private operator-supplied own/peer comparison across 14 services and six editorial signals (84 cells), 1–5 peers, explicit unknown evidence, source references and review-only gap suggestions, 504 synthetic decision cases. No scraping, actual competitor claims, rankings, publishing or database changes. Prior V10.6Z CI passed; new CI and real evidence acceptance pending. See [V10.7A](V107A_COMPETITOR_EVIDENCE.md).
