# V10.8D — tender intelligence and amendment review

Baseline V10.8C 92902580106a2b38319a2afed79ad1f6f738cace. V10.8A cumulative CI passed (45717614512). No schema migration.

## Opportunity ranking
`php secure-setup/maintenance/tender-intelligence.php --rank < /PRIVATE/observations.json`
uses the existing exact V10.7M normalized observation contract, fixed official source registry, 1–25 observations, and all 14 services. Per-service keyword score is 20 + 10 per distinct matched phrase, capped at 60. For an eligible current listed review candidate only, add 20 for exact recognized Mumbai/Navi Mumbai/Thane location labels, 10 for recognized Maharashtra/Pune/Nagpur labels, and 20/10 for the observed deadline within 3/7 days respectively. Cap at 100. Sort by review priority descending, deadline ascending, then reference. Unknown location earns no points; do not infer geography, eligibility, tender value, win probability or ability to deliver. Scores are transparent editorial routing signals only, never qualification. Existing reviewed service preference remains the poll routing decision.

## Amendment monitoring
Enable discovery.tender_intelligence_enabled (default OFF), discovery.enabled, growth.enabled and tender_review_enabled. For scheduled intake, existing authorized tender poll settings and source rights remain mandatory. No new provider or cron is installed. A poll checks the connected database config, exact source settings and amendment flag again before fetching and importing; rechecks pause and running ledger state before each network admission/import. Already-in-flight requests cannot be cancelled.

Only compare current or previous IST-day explicit listed/cancelled/archived observations against the exact original audited revision-1 research draft for the same source/reference. Changes in deadline, title, location, explicit cancellation/archive or unknown deadline become separate pending amendment review tasks with audited deduplication against original facts and material change fingerprint. Never infer cancellation from missing notice or empty feed. Stale/unconfirmed observations and earlier observation dates cannot create amendment tasks. Regenerated/unverifiable original drafts require manual review. Original tasks and historical changes are never automatically rewritten, closed or approved. Comparisons are relative to the original, not an authoritative latest-state history; later reversals and repeated amendments require manual issuer reconciliation. Across-source duplicates, altered service routing, eligibility and actual deadline remain human review. Poll transaction creates new original and amendment review tasks together or rolls back; amendment counts are separately reported, not blended into existing notice ledger counters.

Manual workflow:
`php secure/maintenance/tender-intelligence.php --plan < /PRIVATE/observations.json`
`php secure/maintenance/tender-intelligence.php --apply REVIEWED_SHA256 < /PRIVATE/observations.json`
Plan binds observed rows, current original task revision/draft digest/status and existing amendment task state. Apply requires growth resumed, exact recomputed plan and unchanged config, with existing transaction/savepoint and growth-control locks. All writes are internal pending research tasks.

## Owner queues
`php secure/maintenance/tender-intelligence.php --deadlines` reports recorded IST deadlines of up to latest 250 pending audited original research tasks (not live bid status), with elapsed, within 3 days, within 7 days, later and unverified counts, stale observation flags and explicit truncation. /tenderdeadlines and /tenderchanges expose the same review queues via existing owner-only command flow, up to 10 entries; no automatic alerts sent, bid, payment or outreach. Check official notice/corrigenda independently before acting.

## Verification
168 service/location/urgency and blocked-state ranking cases, exact deadline boundaries, material change/staleness rules and disposable DB poll/amendment/dedup/cancellation/empty-feed/owner queue/config-drift tests authored. New CI pending; no actual tender provider call, bid, or hosted database update. Actual source authorization and scheduler acceptance remain pending.
