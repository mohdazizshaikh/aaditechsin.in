# V9 verification, acceptance and observability

**Status:** acceptance plan; not a claim that V9 tests have run. V8 offline tests exist and staging/live proof remains missing. Every feature implementation PR should map module ID, requirement, test ID, evidence and release switch.

## 1. Test layers

1. **Documentation/static:** links resolve; no contradictory live claims; list all modules, owners, feature flags, schema/API contracts and unverified external dependencies. Original logo hash must not change.
2. **Unit:** pure PHP validation, eligibility, dedup, consent, state transitions, money decimals and AI output schemas, without network or live provider.
3. **Integration:** isolated MySQL staging transactions, role/CSRF/session checks, mock SMTP/AI/API, worker lease/retry, auditing, migrations/upgrades/rollback.
4. **Browser:** desktop/mobile responsive, screen reader/keyboard, guided chat and fallback, multi-step forms, errors, poor connectivity and blocked Google/AI functionality.
5. **Security negative paths:** unauthorized partner, cross-lead access, replay, injection, CSRF, brute force, prompt injection and GDPR/DPDP-related deletion/withdrawal workflow appropriate to policy.
6. **Staging external acceptance:** owner-authenticated actual Hostinger PHP+MySQL, no public secret file, real SMTP accepted+delivered check, verified API access where used, rollback rehearsal and owner signoff. No unapproved production test messages.

## 2. Required test IDs by module

| Test ID | Module | Scenario | Expected evidence |
|---|---|---|---|
| T01-1 | M01 | Every published CTA correct service, mobile and keyboard usable | Captured navigation + automated link check |
| T01-2 | M01 | Unknown/malicious UTM or tracking URL | Ignored or sanitized, no reflected HTML/PII |
| T02-1 | M02 | Seven service playbooks and unknown intent | Schema-valid, approved prompts; human fallback |
| T02-2 | M02 | AI OFF/timeout/unparseable/injected output | Existing deterministic guide, no unauthorized writes |
| T02-3 | M02 | No consent/contact details | No persisted lead; transparent explanation |
| T03-1 | M03 | Simultaneous duplicate submit/key reuse | One reference for same request, 409 on conflicting payload |
| T03-2 | M03 | Two legitimate customers same company/contact | Flag, no destructive auto-merge |
| T03-3 | M03 | Partner tries to read another lead | Denied server-side with no PII |
| T04-1 | M04 | Consent withdrawn after scheduling | Suppressed before send; audit outcome |
| T04-2 | M04 | SMTP 5xx, timeout and duplicate cron | Retry cap; no fabricated delivered status or silent duplicate |
| T04-3 | M04 | Marketing flag OFF / hard bounce | Zero outbound, owner queue intact |
| T05-1 | M05 | Partner attempts work without approval | 403/invalid transition, source of truth unchanged |
| T05-2 | M05 | Owner price change after approval | Reopens quote and re-approval evidence required |
| T05-3 | M05 | Link rotated/expired; partner disabled | Old link invalid; old session revoked |
| T06-1 | M06 | Non-allowlisted/blocked source URL | Fetch denied and logged, no source bypass |
| T06-2 | M06 | Duplicate or expired tender | Single watchlist entry, no outbound contact |
| T07-1 | M07 | AI draft contains unverified certification/review | Rejected before publication |
| T07-2 | M07 | Provider reports publish error | Remains pending, no “published” badge |
| T08-1 | M08 | AI tries budget increase without owner approval | Denied, no API write and no expense |
| T08-2 | M08 | Multiple retries, currency/geography mismatch | Campaign remains OFF; approval binding validated |
| T09-1 | M09 | Quote issued, nothing collected | Pipeline estimate only; zero collected revenue |
| T09-2 | M09 | Partial collection/refund/additional partner bill | Correct ledger reconciliation, no double count |
| T09-3 | M09 | Partner/public asks for profit report | Denied server-side |
| T10-1 | M10 | Malicious text requests SQL/send/spend | No tool execution, harmless refusal/escalation |
| T10-2 | M10 | Model failure/token exhaustion | Bounded fallback, metrics and owner review |
| T10-3 | M10 | Kill switches OFF | No provider outbound calls across modules |

## 3. Baseline V8 regression suite (run on each code change)

```bash
for f in public_html/assets/*.js; do node --check "$f"; done
find public_html secure-setup -name '*.php' -exec php -l {} \;
php tests/workflow_rules.php
python tests/security_static.py
python tests/site_integrity.py
python tests/http_smoke.py
python tests/browser_smoke.py  # requires Playwright + browser
```

Only report PASS on commands actually run. The new V9 module tests will be added alongside code; these test IDs are a contract, not pre-existing files. Schema migrations must be tested against both fresh V8 and an upgraded *staging* DB with independently verified backups.

## 4. Threat, failure and chaos drill

- **DB unavailable:** public pages still work, form says “not submitted” and offers phone/email; no fabricated reference.
- **AI service unavailable 30 minutes:** deterministic guide still captures request after consent; retry budget/circuit breaker prevents stalled PHP workers.
- **SMTP unavailable 24 hours:** leads visible in DB/owner queue; worker reschedules safe attempts, never falsely marks delivered.
- **Cron paused/overlapping:** due tasks accumulate visibly; execution lock and idempotency prevent duplicate sends after restart.
- **Ads provider unreachable:** no background spend mutation; owner sees unknown status, uses native ads account for urgent pause.
- **Compromised partner password:** account disable/session version changes revoke sessions; assigned-case access blocked.
- **Tracking link leaked:** rotate invalidates immediately; no amount/customer contact or notes exposed.
- **Marketing consent withdrawn:** queued messages suppressed before dispatch and subsequent audience import blocked.
- **Money correction:** ledger reversal produces correct net/period report with audit actor/evidence.
- **Backup recovery:** restore into isolated staging without redirecting DNS or losing new live submissions; verify data reconciliation procedure before any live rollback.

## 5. Observability and owner alert thresholds

Proposed lightweight private dashboard; avoid heavy monitoring infrastructure. Track daily `new_leads`, `unreviewed_leads_age`, `lead_save_error_rate`, `outbox_due_count`, `outbox_failed_count`, `ai_fallback_rate`, `cron_last_success`, `login_denials`, `campaign_actual_spend`, `estimated_margin`, `realised_margin` and `jobs_past_due`.

Start with **operator-configurable** warning thresholds, not hardcoded SLA promises. Example pilot defaults (revisit with actual baseline): any accepted lead without owner review after 2 business hours → owner reminder; cron missing two expected windows → warning; external side effects occurring while kill switch OFF → critical incident. Send alerts to owner inbox only after delivery is tested.

## 6. Evidence format for every release

| Field | Required value |
|---|---|
| Source version | Full Git SHA, branch and changed files |
| Feature | Module IDs and exact flags enabled |
| Data | Migration IDs, schema checksum, backup ID and restore result |
| Tests | Unit/integration/browser/security report links, actual PASS/FAIL counts |
| Security | Secrets scan, role tests, opt-in and abuse controls |
| External | Hostinger staging URL, verified SMTP inbox, provider permission outcome when relevant |
| Owner approval | Date, approver, service/campaign budget/partner/legal scope |
| Rollback | Prior SHA, DB compatibility decision, disable switches, responsible operator |

Do not publish internal test screenshots with real customer email/phone or admin dashboard content to the public repo. A failure is a blocker; an unexecuted test is **NOT TESTED**, never PASS.

## 7. Exit criteria and signoff

- **Code ready:** test suite green, all security defaults OFF, no production secrets, backwards-compatible API/docs, module code review.
- **Staging ready:** real hosting acceptance on synthetic data, backup restore, approved copy/privacy, negative permission tests and reliable fallback.
- **Production ready:** explicit live cutover approval, effective rollback, monitored launch, enabled feature-by-feature. Google/Meta/Ollama availability is not assumed.
