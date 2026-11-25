# V10.6Y — Reviewed communications batches and retention integrity

Source-only continuation from V10.6X. Prior incremental #62 and cumulative #63 passed CI (45578648418, 45578657826). No migrations (36 migrations, 49 tables, 38 pinned hashes). No provider access, live messages, calls, cleanup or deployment.

## Private review batch
New communications.review_batch_enabled defaults OFF; growth, communications and reviews must also be enabled. Authorized operator only. Private JSON example: {"version":1,"action":"set","items":[{"channel":"calls","day_utc":"YYYY-MM-DD"}],"due_on":"YYYY-MM-DD"}. Supported actions set (create/reopen/reschedule), complete, cancel; latter two omit due_on. Select 1–50 distinct channel/day pairs, email/whatsapp/sms/calls only. Exact decoded JSON fields, 16 KiB maximum. Set requires completed UTC date in the existing 90-day import window, due today through 90 days ahead, positive missed count, active growth; same-date open reviews refused. Complete/cancel require open reviews and may close older reviews while paused.

Run `php secure/maintenance/communication-review-batch.php --plan < /PRIVATE/reviews.json` for read-only aggregate/review state, canonical ordering and private digest. Review scope and aggregate evidence. Run `php secure/maintenance/communication-review-batch.php --apply REVIEWED_SHA256 < /PRIVATE/reviews.json` to lock control and selected rows and revalidate exact state. All writes and per-item audits commit together. Stale aggregate counts, review metadata, pause state or scope reject; inspect state after uncertain commit. Digest binds current state, not historical changes or authentication. No automatic scheduling, recipient data or messaging.

## Retention integrity
Existing communications retention now binds complete eligible aggregate fields, closed review state and Gmail count snapshot into a versioned digest. Old digests no longer apply. Open eligible reviews still block deletion. Individual communication import and review mutations and retention apply have local savepoints for audit failure. Single review closure acquires growth control lock before mutation, consistent with retention/import/batch ordering. Existing default-OFF retention and paused requirement unchanged; actual approved policy and verified backup/restore mandatory. Plan output remains summary-only; no actual deletion in development.

## Verification
108 offline scope cases and 24 per-channel disposable database cases, multi-row close while paused, digest drift, and five retention refusal checks authored. These are test scenarios, not independent features. CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45607405958 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45607412363. Actual owner approval, consent, staging and provider/retention acceptance remain open.


## V10.6Z — private page-level SEO diagnostics (source only)
Optional detailed seven-page observation v2 with strict path/status/finding consistency, aggregate-only persistence, v1 compatibility, atomic Search Console snapshot/history/audit and live SEO audit rollback. 56 crawler + 126 report validation scenarios authored. Prior V10.6Y CI passed; new CI and actual crawl/provider/staging acceptance pending. See [V10.6Z](V106Z_PAGE_SEO_DIAGNOSTICS.md).
