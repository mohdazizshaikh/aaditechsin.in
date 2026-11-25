# V10.6X — Reviewed opportunity reminder batches

Source-only continuation of V10.6W. V10.6W incremental #60 and cumulative #61 CI passed (45569454303, 45569458258). No migrations, external requests, outreach, credentials or deployment.

Private opt-in: discovery.enabled, growth.enabled, discovery.followups_enabled and discovery.followup_batch_enabled must be true. New flag defaults OFF. Use an already authorized operator and private input:

- Set/reschedule: {"version":1,"action":"set","ids":[123,456],"due_on":"YYYY-MM-DD"} (today through 90 UTC days).
- Complete/cancel: {"version":1,"action":"complete","ids":[123,456]} or action cancel.
- `php secure/maintenance/followup-batch.php --plan < /PRIVATE/reminders.json` reads current status and returns normalized sorted IDs, service IDs, reminder status/due dates, pause state and a SHA-256 digest. No database writes; plan rolled back.
- Review all selected records. `php secure/maintenance/followup-batch.php --apply REVIEWED_SHA256 < /PRIVATE/reminders.json` locks control then opportunity/reminder rows in ID order and recomputes the exact plan. All selected changes and per-item audits commit together or roll back. No blind retry after uncertain commit; inspect state first. Digest is not authentication or a signature, and binds current state rather than history (an identical restored state can match).
- 1–50 distinct positive integer IDs, 8 KiB scope limit, exact decoded object fields, strict version/action/date; no organization names, contacts or evidence in the plan. New sets require active growth and qualified opportunities; identical open date rejected; closing requires an open reminder, allowed when paused. Pause changes invalidate reviewed digest. Existing owner single commands remain compatible; their changes and opportunity qualification/dismissal now use local audit savepoints.
- No automated customer communication, opportunity requalification, scheduling, history table or provider calls. Do not treat reminders as verified sales intent.

Validation authored: 73 offline scope cases and 84 per-service disposable database cases plus multi-row close, pause/digest and existing regression checks. These are test scenarios, not 157 independent features. CI for this commit pending. Actual private CLI, owner authorization, staging, provider acceptance, retention and recovery remain open. Existing 36 migrations, 49 tables, 38 pinned hashes unchanged.
