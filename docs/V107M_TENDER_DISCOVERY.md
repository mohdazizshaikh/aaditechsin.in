# V10.7M — public procurement discovery and owner review

V10.7L final source CI passed: 45689048951, 45689084124, 45689093251. Main and production unchanged.

## Real public observations (26 November 2026 IST)

Official source portals and observation scope:
- [Mumbai Port Mechanical/Electrical tenders](https://www.mumbaiport.gov.in/show_tenders.php?catid=3&depid=2&lang=1): 1 relevant listed notice, MEED. 38/2026, biometric access and CCTV, listed close 16 Dec 2026 15:00 IST.
- [NICSI GeM bids](https://nicsi.nic.in/nicsi/nicsi-gem-bids/): five relevant visible bids, GEM/2026/B/8294896 (AV/IT equipment), /8289562 (storage/network), /8287578 (network gateway), /8258685 (VC equipment), /8272986 (laptop and VC camera). Dates from issuer's list; location not stated on listing. Not assumed Mumbai. Verify individual bid documents and corrigenda on GeM.
- [Maharashtra eProcurement](https://mahatenders.gov.in/nicgep/app): official homepage showed corrigendum AMC/ES/4400 dated 19 Nov, with revised 30 Nov 16:00 deadline. [Atlenza secondary listing](https://app.atlenza.com/tender/the-work-of-modernization-of-audio-visual-recording-and-live-e8e40b0ff060457486259ea12a191471) supplied the AV scope and reference 2026_MCGM_1357116_1. Kept unconfirmed until official full tender and identifier are checked together.
- [Mumbai Port EDP archive](https://mumbaiport.gov.in/tendersArc.php?arch=1&catid=3&depid=4&lang=1&level=0): October telecom AMC EOI is archived/closed. Example local descriptive reference EOI TELECOM AMC OCT 2026 is not an official tender ID.
- [CPPP](https://eprocure.gov.in/eprocure/app) official central procurement search and [GeM BidPlus](https://bidplus.gem.gov.in/all-bids) manual entry routes. Direct GeM listing timed out in this research; no API/access grant was confirmed.

Dated example [observations](examples/tender-observations-2026-11-26.json) contains eight paraphrased public observations: six listed potential review candidates, one unconfirmed and one archived. All selected_service fields deliberately null. No personal contacts or supplier eligibility claims. No actual tender qualification, submission, or live database import. Deadlines are observations not guarantees; check amendments immediately before acting. Re-observe from official source, never just change observed_on to keep stale data alive. Research scope is not exhaustive.

## Working offline shortlist and task bridge

The new CLI uses 43 tender-specific phrases in addition to the existing 14-service matching catalog. The general discovery matcher is unchanged. Use private locally prepared JSON from manual authorized research or a permitted provider export (1–25 rows). Exact version 1/items, each source (fixed allowlist), reference (uppercase stable public bid identity, not a contact number), title (short factual scope), location (or Not stated), observed_on (IST date), deadline_ist (local YYYY-MM-DDTHH:MM:SS or null), state (listed/unconfirmed/archived/cancelled), selected_service (null or one matched service). No arbitrary fetch URL, query interpolation, network or CAPTCHA automation. The source_url is a fixed official listing link, not necessarily a unique document URL. Match is a keyword signal only. Records sorted by eligibility and closing time, urgency within 3 days / 7 days / later. Explicit blocks for expired, archived, cancelled, stale observation (older than previous IST day), unconfirmed, unknown deadline and no match.

`php secure-setup/maintenance/tender-review.php --shortlist < /PRIVATE/observations.json`

Read-only offline; no config/database needed. Review real tender identity, latest status, service match, location, eligibility, OEM/supplier requirements, financial and delivery capacity. The shortlist is not verified bidding intent or permission. On authorized private staging with growth/discovery enabled and separate discovery.tender_review_enabled flag (default OFF), select one matched service per verified candidate, then:

`php secure/maintenance/tender-review.php --plan < /PRIVATE/selected.json`

`php secure/maintenance/tender-review.php --apply REVIEWED_SHA256 < /PRIVATE/selected.json`

Plan rolls back; apply locks growth control and existing matching research task rows, rechecks current IST date/deadline/selected match, exact plan digest, creates only pending research tasks with reference, official listing, observed deadline and eligibility checklist. Existing task preserved, even if approved or rejected; manually review amendments. Dedup uses fixed source + uppercase reference, across selected services within that source. Same tender appearing across multiple portals requires operator dedup before selection. No database uniqueness constraint; use single authorized operator, reserve task title prefix, do not insert overlapping tasks through other tools. No outreach, bid submission, payment, provider login or new migration. No new automated feed authorization. User-supplied state is an assertion, not independently verified by code.

## Verification and next steps

Test 43 additional aliases, deadline and state classification, duplicate and malformed input, synthetic DB reviewed plan/apply/pause/expiry/dedup/rollback. CI pending. Actual source-provider permission, automated scheduled ingestion, official document recheck, real owner review, and hosted operation remain pending. Source coverage of all 14 services is not a percentage of whole project.

Owner command `/tenders` lists up to ten pending tender research tasks with readable source/reference and `/task ID`. This is stored review inventory, not current bid status; owner must revisit official source. Dated public fixture added to CI to verify six review candidates, one unconfirmed and one archived, none preselected; later date confirms staleness. Archive listing cannot be supplied as active. Canonical references reject leading/trailing and repeated spaces. No actual owner bot delivery.


## V10.7N — scheduled tender ingestion (source)
Authorized normalized HTTPS tender feed worker (1–5 sources, 25 notices/source), optional private bearer, reviewed service routing, cadence, atomic pending research task/ledger write and owner queue. V10.7M all CI passed. No actual provider connection or cron installed; final CI pending. See [V10.7N](V107N_SCHEDULED_TENDER_INGESTION.md).
