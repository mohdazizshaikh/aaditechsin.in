# V10.6Z — Private page-level SEO diagnostics and atomic search evidence

Source-only continuation of V10.6Y, whose incremental #64 and cumulative #65 CI passed (45607405958, 45607412363). No migrations (36 migrations, 49 tables, 38 pinned hashes), actual HTTP requests, Google access, or deployment.

## Operator workflow
The existing authorized seven-page crawler stays fixed to aaditechs.in, no redirects, robots policy and pinned TLS unchanged. Default output v1 remains compatible. Optional `python3 workers/live-seo-crawl.py --capture-authorized --page-detail` returns v2 private JSON with exactly seven ordered path/status/finding rows. No raw page contents, titles, descriptions, external URLs, customer data or credentials. Inspect per-page missing title/description, H1 count, exact canonical, meta noindex and absent alt attributes. Distinguish non-200 from unavailable; do not infer findings for uninspected pages. Not full rendered browser, uptime, ranking, accessibility, competitor or link audit.

Existing private `live-seo-import.php --import` accepts v1 and v2 under existing OFF-by-default gates, max 8 KiB. V2 independently derives aggregate counts from exact fixed paths, status and unique allowlisted findings, refuses inconsistent totals, unknown fields, missing/reordered paths and findings on uninspected pages. Only existing aggregate counts and timestamp persist, not page details. Report remains operator-imported evidence rather than network attestation. Keep detailed output private for remediation review. No automated remediation.

## Search evidence
Snapshot, optional history and audit now share a local savepoint; history cap is checked before the first history row write. History writes require a transaction and active growth. Explicit null/non-list rows and malformed row/key shapes rejected; missing rows still means no returned observations. Live SEO aggregate capture and audit also share a local savepoint. No actual Search Console requests or changes to authorization rules.

## Verification
56 mocked page-specific crawler cases and 126 PHP detailed-report contract cases authored, plus malformed Search Console row checks, v1 compatibility and aggregate-only disposable DB storage. New CI pending. Tests are not independent implementation tasks. Actual crawl consent, Search Console permission, competitor intelligence, local AI, backup/restore and hosting acceptance remain open.
