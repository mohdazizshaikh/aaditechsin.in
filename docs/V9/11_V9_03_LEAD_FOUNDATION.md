# V9-03 — additive lead intake, consent and retry-safe CRM foundation

**Source status (23 Nov 2026):** implemented behind **independent OFF switch** for review. **SQL NOT APPLIED; ENDPOINT NOT DEPLOYED; NO CUSTOMER DATA PROCESSED.** Hostinger test explicitly deferred by owner. This is not a production-ready API claim.

## What exists in source

| Component | Path | Design and safeguards |
|---|---|---|
| Additive V9-001 migration | `secure-setup/migrations/2026-11-23-v9-001.sql` | Five new InnoDB/utf8mb4 tables; no changes to V8 tables or data |
| Pure validation and hashing | `public_html/api/v9/lead_rules.php` | Exact service allowlist, explicit inquiry consent, no marketing default, bounded fields, sanitized attribution, normalized payload hash |
| New optional public API | `public_html/api/v9/leads.php` | `POST` only; HTTPS, same-origin, 8 KiB request cap, JSON, UUID/128-bit idempotency key, honeypot, salted per-IP 5-minute throttle, DB transaction and safe failure codes |
| Private config template | `secure-setup/aaditechs-config.php.example` | Separate `v9.lead_intake_enabled=false` and `v9.privacy_notice_version=REPLACE_...`; V8 flags remain OFF |
| Offline test scaffolding | `tests/v9_lead_rules.php`, `tests/v9_backend_source_check.py` | Synthetic validation, consent, idempotency hash, attribution and migration invariants; CI PHP lint/PHP rules when environment allows |

No new email, partner dispatch, quote, marketing send, AI or campaign write is attached. V8 `/api/lead.php` stays unchanged and the public chat continues to use V8 in demo mode until a separately approved adapter/cutover. No API secrets, raw IPs, passwords or live customer records are committed.

## Schema and transaction boundaries

V9-001 creates:

1. `lead_events` — immutable created/status/owner activity slots (initial public creation event only).
2. `lead_attribution` — one record per consented lead, safe landing path and allowlisted UTM strings/referrer hostname, never full referrer URLs.
3. `lead_consent` — explicit `inquiry_contact` granted event and privacy notice version; marketing consent is **not granted** by an inquiry.
4. `contact_suppression` — future opt-out/notification gate table; **not yet actively enforced** because outbound V9 messaging is still absent. Never treat its existence as a working opt-out route.
5. `lead_requests` — HMAC-hashed idempotency key, HMAC-hashed canonical payload, lead reference relation, seven-day expiry marker. **No cleanup job is installed yet.**

Successful intake inserts a V8 `leads` row (canonical `service_code` in existing `service` column), idempotency row, `created` event, contact consent and safe attribution in **one transaction**. A DB exception rolls back. Request retry with the same key and identical normalized details returns the same reference (`200`); reused key with different details gets `409`, not a second lead. New accepted submission returns `201`. The throttle is checked after replay lookup so ordinary retries don't hit the new-lead rate limit. SMTP is deliberately not part of this transaction.

**Important limitation:** concurrent different-IP requests can briefly race on the unique request key. One transaction wins and the other rolls back; a retry after the winner commits retrieves the reference. No duplicate lead should be committed from that unique-key conflict, but a transient `503` is possible; prove this in a real MySQL concurrent test before release.

## Request contract (future integration, synthetic only)

HTTP `POST /api/v9/leads.php`; header `Idempotency-Key: 6748c100-3e4e-42ef-b7ca-2f77d8dd44da`; `Content-Type: application/json` over same-origin HTTPS. The header is a **per-submit random** UUID or 128-bit hex string, retained by the browser while retrying that specific submission, not reused for new enquiries.

```json
{
  "service_code": "VC",
  "fullName": "Example Person",
  "email": "example@example.invalid",
  "phone": "",
  "device": "",
  "message": "A meeting room for about 20 people",
  "source": "guided-chat",
  "contact_consent": true,
  "marketing_email_opt_in": false,
  "notice_version": "OWNER_APPROVED_STAGING_VERSION",
  "answers": {"platform": "Teams", "room_capacity": "20"},
  "attribution": {
    "landing_path": "/video-conferencing-solutions/",
    "utm_source": "google",
    "utm_medium": "organic",
    "utm_campaign": "local_vc",
    "utm_content": "service_page",
    "referrer_host": "www.google.com"
  }
}
```

All `answers` values are optional and bounded (maximum 8 short keys, 300 characters each). Safe answers are appended to `leads.message` for owner review but total must fit 2,000 characters or validation fails. No raw chat transcript is persisted before explicit consent. `marketing_email_opt_in=true` is currently rejected until a separate opt-in flow and withdrawal mechanism exist. Do not map V9 payload to V8 without an approved compatibility adapter.

Response examples:

```json
{"ok":true,"data":{"reference":"ATL-EXAMPLE","next_step":"Owner review pending"},"request_id":"req_example"}
{"ok":false,"error":{"code":"CONFLICT","message":"This request key was already used for different details"},"request_id":"req_example"}
```

The API returns no SQL error, contact PII or private server configuration. PHP server logs receive only a generic persistence-failure statement.

## Activation / staged test (NOT PERFORMED)

1. Authorize Hostinger staging and verify actual PHP 8.1+, PDO MySQL, HTTPS, sibling `secure/`, snapshots and backup restore. Do **not** modify live `aaditechs.in`.
2. Record canonical V8 schema after `2026-11-23-v8.sql` if applicable. Compare each V9 table definition against any existing table; `CREATE TABLE IF NOT EXISTS` alone is **not** schema reconciliation.
3. Back up and restore-test staging. Review privacy notice, retention, suppression and consent text. Import **only** V9-001 into **staging** after this gate. Rollback of business data is **backup restore or reviewed explicit migration**, never unreviewed drops.
4. Keep V8 database/leads disabled initially. Put restricted actual config under `secure/` outside webroot. Set `v9.privacy_notice_version` privately to the exact owner-reviewed version; do not publish secrets in docs/chat.
5. Enable staging `database.enabled`, `leads.enabled` and **separately** `v9.lead_intake_enabled` after HTTPS security/consent review. Leave production flags OFF.
6. Test validation failures; invalid/missing consent, tampered Origin, unsupported service, malformed JSON, oversized payload, honeypot, 429 rate limit, same-key exact replay, same-key changed payload `409`, two-worker race, transaction rollback and no persistence before consent. Verify `lead_events`, `lead_consent`, attribution and one-row idempotency in a private test DB.
7. Check V8 owner/partner job controls and V8 `prune.php`. New V9 foreign keys are **RESTRICT**: existing V8 pruning must be revised with owner-approved retention policy before enabling cleanup. `lead_requests.expires_at` is only a marker; cleanup is not installed.
8. Only after all gates and owner signoff, implement/test frontend adapter and planned owner CRM inbox. There is **no automatic cutover**.

## Test evidence and honest gaps

Local container: `python3 tests/v9_backend_source_check.py` **PASS** (source invariants), `node tests/v9_service_playbooks.test.js` **PASS**, `python3 tests/security_static.py` **PASS**. PHP executable was **not installed** in this container; `tests/v9_lead_rules.php`, PHP lint, transactions, DB concurrency, SMTP, Hostinger and privacy acceptance were **NOT RUN** here. GitHub source-only CI is configured to execute PHP lint and the new pure PHP rule tests; check actual CI results before saying these passed.

**Next slice:** V9-04 owner-only inbox, triage and explainable scoring (after actual V9-03 staging). Separately design/implement opt-out + cleanup before V9-05 message outbox; do not send unsolicited contacts.
