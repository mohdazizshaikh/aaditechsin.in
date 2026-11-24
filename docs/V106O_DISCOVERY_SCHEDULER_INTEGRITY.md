# V10.6O discovery scheduler readiness and evidence integrity

Source-only continuation of V10.6N. Both prior CI runs passed on d8a11474632862a5defe846707ef9d96cc09a6cd ([run 1](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494808181), [run 2](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494801920)). No schema migration: 36 migrations, 49 tables, 38 pinned schema/fixture/migration hashes.

## Explicit scheduler contract

New private discovery.scheduler_enabled defaults OFF, independent of discovery/feeds/cadence. Existing --dry-run and manual --run retain prior semantics. For scheduling, configure 1–5 separately authorized sources, cadence_enabled=true and integer minimum_interval_minutes 60–10080. Private CLI:
- --schedule-check: validates scheduling configuration, no network/DB.
- --schedule-status: read-only advisory DB view, per-source due/not_due/unresolved/paused and last UTC attempt. Not a scheduler execution proof; state can change.
- --scheduled-run: explicitly authorized scheduled invocation, shares existing nonblocking file lock, source ledger admission lock, pause and cadence gates, bounded HTTPS transport. No automatic retry, no outreach, no installation.

After actual staging and permission review, owner may schedule a fixed private command (e.g. hourly) through an approved hosting scheduler, with private working directory, restricted logs and no command-line secrets. Cadence governs attempts rather than successful imports. Stop scheduler and workers before reviewed recovery or maintenance. No cron was installed; no live provider accessed.

## Evidence integrity
Reject invalid UTF-8 in every evidence field. Savepoint atomically binds new opportunity and audit, and outreach draft, link and audit even if a caller catches a failure. Acquire growth-control row lock before opportunity row for draft creation, matching follow-up/worker order and fail closed for missing/paused control. Human qualification remains separate; no outbound sending.

## Verification
126 new synthetic evidence normalization scenarios (14 services × nine valid/invalid scenarios), plus 14 synthetic intake/dedup pairs, scheduler configuration denial and read-only due/recent/paused/unresolved cases. Prior tests retained. New CI pending; no local PHP/MariaDB run in this session. These are regression cases, not 126 distinct production tasks.

P0 isolated hosted restore and staging; P1 live authorized sources, scheduler setup, local AI/native video, provider consent, OAuth, retention and GBP permissions remain pending. No merge/deployment.
