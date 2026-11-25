# V10.8A — authorized private competitor editorial capture

Baseline V10.7Z ab0d5d49ba77adced4aaabc4b8f9da486ba73ebb. V10.7X cumulative CI passed (45716212738); Y/Z queued at last check. No schema changes.

## Scope and authorization
Obtain owner authorization and review each site's terms and robots policy before any automated requests. Select aaditechs.in and one to five exact peer HTTPS hostnames. Review the exact robots.txt bytes independently and record SHA-256. No login, API, private routes, credentials, query parameters, fragments, redirects, browser execution, media download, or crawling links. Do not use this tool if the owner lacks necessary authorization or site policy prohibits automated access. A digest is not a permission attestation. Explicit same-day plan and authorization per host:
```json
{"version":1,"scope":"authorized-editorial-capture","review_day_utc":"2026-11-26","subjects":[{"host":"aaditechs.in","authorized":true,"robots_sha256":"REPLACE_WITH_REVIEWED_SHA256","pages":[{"service_id":"it-amc","url":"https://aaditechs.in/it-amc/"}]},{"host":"peer.example","authorized":true,"robots_sha256":"REPLACE_WITH_REVIEWED_SHA256","pages":[{"service_id":"it-amc","url":"https://peer.example/it-amc/"}]}]}
```
Synthetic example only. Use real current UTC date and verified exact paths. Up to 14 distinct service pages per host, 84 total. Use private 0700 directory and umask 077 for redirected files. No files in public webroot or Git.

`python3 workers/competitor-capture.py plan < /PRIVATE/scope.json > /PRIVATE/plan.json`

Review the exact plan SHA-256 and scope; no network yet.

`python3 workers/competitor-capture.py capture --expected REVIEWED_PLAN_SHA256 < /PRIVATE/scope.json > /PRIVATE/capture.json`

Before any page, resolve each host to public IPv4 only, reject unsafe/mixed answers, pin chosen address for HTTPS with exact TLS hostname, fetch and verify exact reviewed robots bytes. Conservative supported directives only: user-agent, allow, disallow, sitemap; any matching disallow wins, wildcard/anchor rules and unknown directives block. No redirects, compressed or restricted responses, bounded 32 KiB robots and 512 KiB HTML, UTF-8, at least two seconds between requests; DNS and individual socket timeouts are not a hard total process deadline. Abort entire capture on any refusal or UTC-day change. No automatic retries, no scheduler installed. No raw page text, contact data, links or HTML retained; output only exact URL, day, body digest, heading/link counts and six unknown signals.

## Editorial review handoff
`python3 workers/competitor-capture.py review-template < /PRIVATE/capture.json > /PRIVATE/decisions.json`

Review actual permitted page content independently; digest records captured bytes but no stored raw snapshot exists to prove a subsequent human saw those exact bytes. If content changed or uncertain, leave signal unknown or recapture. Edit only present/absent/unknown signal values in the decisions file; do not add subjects/pages or change URLs/dates. No automatically inferred absence, quality, ranking, traffic, market share or competitor claims.

`python3 workers/competitor-capture.py review --expected CAPTURE_SHA256 --decisions /PRIVATE/decisions.json < /PRIVATE/capture.json > /PRIVATE/evidence.json`

The review template contains capture_sha256 (canonical JSON digest). The reviewed evidence output is compatible with V10.7A comparison and V10.7B reviewed task scope. Owner independently approves any editorial change; no automated publishing.

## Verification
84 individual editorial cell handoffs, 140 invalid URL cases, 56 review scope drift cases, bounded robots/DNS/HTML fixtures and redaction checks authored. CI pending; no actual competitor pages fetched by this worker, no live scheduled capture or production release.
