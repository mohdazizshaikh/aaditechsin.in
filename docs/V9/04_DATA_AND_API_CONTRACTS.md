# V9 database and API contracts

**Status: PROPOSAL ONLY — DO NOT IMPORT AS SQL.** Keep V8 schema unmodified until each numbered, reversible V9 migration is reviewed, tested and backed up. No live endpoint under `/api/v9/` exists yet. Amounts are decimal INR, never floating-point JavaScript-derived authoritative totals. Store UTC timestamps; display Asia/Kolkata.

## A. Existing authoritative V8 tables and fields

| Table | Key fields | Invariant |
|---|---|---|
| `leads` | `id`, unique `reference`, `full_name`, `email`, `phone`, `service`, `device`, `message`, `source`, `status`, `consent_at`, timestamps | `status` only `new`, `qualified`, `responded`, `converted`, `closed`; owner holds contact PII |
| `partners` | `id`, `display_name`, unique `login_email`, `password_hash`, `specialties`, `is_active`, `session_version` | Reset/disable revokes sessions; password never plain text |
| `jobs` | unique `case_code`, `lead_id`, `partner_id`, scope, `status`, `customer_quote`, `partner_cost`, tracking hash/expiry | Server-enforced approval gate; no automatic AI price/approval |
| `job_activity` | `job_id`, `actor_role`, `actor_label`, `event_type`, `note`, timestamp | Only authorized owner/partner audit, no public access |

Before adding anything, confirm V8 staging migration `secure-setup/migrations/2026-11-23-v8.sql` is applied only where needed and compare actual DB structure to canonical `secure-setup/schema.sql`.

## B. Small additive migrations — order and minimum schema

Implement as separately reviewed versioned migrations. Columns below are **proposed**, not assertions that the DB already has them. All tables require InnoDB, utf8mb4, prepared SQL, indexes on foreign keys/due timestamps and appropriate retention policies. Foreign keys to V8 `leads`/`jobs` use RESTRICT for business records; avoid cascade-deleting auditable transactions.

### Migration V9-001 — CRM/events and attribution (P0)

`lead_events`:

| Column | Type/design | Purpose |
|---|---|---|
| `id`, `lead_id` | BIGINT PK / FK | Trace lead change |
| `event_type` | VARCHAR(50) allowlist | `created`, `classified`, `contacted`, `status_changed`, `owner_note`, `duplicate_flagged`, `consent_changed` |
| `actor_type`, `actor_ref` | VARCHAR(24/100) | `owner`, `system`, `customer`, never arbitrary role |
| `public_safe_note` | VARCHAR(500) nullable | Minimal internal note; no secrets |
| `created_at` | DATETIME UTC | Audit/time measurement |

`lead_attribution`: `lead_id` UNIQUE FK, `landing_path` VARCHAR(180), allowlisted `source`, `medium`, `campaign`, `content` (VARCHAR bounded), `referrer_host` (not full sensitive URL), `first_seen_at`, `last_touch_at`. Do not collect raw IP as marketing identity; store validated values only after consented lead creation.

`lead_consent`: `id`, `lead_id`, `purpose` ENUM(`inquiry_contact`,`marketing_email`,`review_request`), `decision` ENUM(`granted`,`withdrawn`), `notice_version`, `source`, `occurred_at`, `evidence_ref` nullable. **Inquiry contact permission does not automatically permit marketing email.** Latest decision by purpose is authoritative. Add per-channel `contact_suppression` (`contact_hash`, `channel`, `reason`, `created_at`, `expires_at` nullable) with protected salt outside Git.

### Migration V9-002 — tasks and delivery outbox (P0)

`tasks`: `id`, `lead_id` nullable, `job_id` nullable, `type` enum (`owner_review`,`followup`,`assessment`,`quote_review`,`partner_check`,`content_review`), `status` enum (`open`,`in_progress`,`done`,`cancelled`), `priority`, `due_at`, `assigned_to='owner'`, `created_by`, `notes` minimal, `created_at`,`updated_at`, optional `version` for conflict detection.

`message_outbox`: `id`, `lead_id`, `channel`, `purpose`, `template_key`, `template_version`, `payload_ref` protected/not large raw body, `idempotency_key` UNIQUE, `status` enum (`queued`,`leased`,`accepted`,`delivered`,`failed`,`suppressed`), `due_at`, `lease_until`, `attempt_count`, `provider_message_id` nullable, `last_error_code` sanitized, UTC timestamps. Unique `(lead_id, purpose, template_key, idempotency_key)` as appropriate; dedupe must not suppress a legitimate newer revision.

`message_attempts`: `id`, `outbox_id`, `attempt_no`, `started_at`, `finished_at`, `result_code`, `provider_ref` nullable. No full provider response containing personal data or secrets in general logs.

### Migration V9-003 — editorial/discovery (P1/P2)

`content_drafts`: `id`, `service_code`, `channel`, `title`, `draft_text` (reviewed retention), `source_fact_refs` JSON bounded, `status` enum (`idea`,`draft`,`needs_review`,`approved`,`published`,`rejected`), `owner_approved_at` nullable, `provider_ref` nullable, schedule timestamp, version, timestamps. Only approved factual claims can be published. Public pages remain actual source files until a CMS requirement is approved.

`opportunities`: `id`, `source_kind`, `source_url`, `source_hash` UNIQUE, `retrieved_at`, `source_terms_basis`, `service_code`, `location_region`, `deadline_at` nullable, `summary` limited, `score_reason`, `review_status` enum (`new`,`reviewed`,`pursue`,`discarded`,`expired`), `owner_reviewed_at`. No harvested contact database.

### Migration V9-004 — finance and campaigns (P1/P2)

`job_financial_entries`: `id`, `job_id` FK, `kind` enum (`customer_invoice_net`,`customer_collection`,`customer_refund`,`partner_payment`,`direct_cost`,`cost_reversal`), `amount` DECIMAL(12,2) positive, `currency` VARCHAR(3) default `INR`, `tax_excluded` boolean + policy, `reference` unique per provider/domain where possible, `occurred_at`, `recorded_by`, `created_at`, `correction_of_id` nullable. **Append correction entries; do not silently edit historical posted money.** Do not infer cash collection from `jobs.customer_quote`.

`campaigns`: `id`, `platform`, `name`, `service_code`, `geography`, `landing_path`, `currency`, `approved_daily_cap`, `approved_total_cap`, `owner_approval_ref`, `status` enum (`draft`,`awaiting_approval`,`approved`,`active`,`paused`,`completed`,`rejected`), `provider_id` nullable, `created_at`, `updated_at`. `campaign_spend` separate periodic fact table (`campaign_id`, `period_start`, `period_end`, `amount` DECIMAL(12,2), `source`, `retrieved_at`, unique provider statement reference). In V9 first release this data is owner-entered/read-only; no API spend mutation.

### Migration V9-005 — AI and runtime audit (optional P2)

`automation_runs`: `id`, `kind`, `correlation_id` UNIQUE, `status` enum (`queued`,`running`,`succeeded`,`failed`,`suppressed`), `started_at`, `finished_at`, `redacted_error`, `retry_after`, `owner_approval_ref` nullable. `ai_usage`: aggregate `provider`, `model`, `prompt_revision`, `task_type`, `tokens_in`, `tokens_out`, `duration_ms`, `status`, `created_at`, no raw prompts/PII by default.

## C. Idempotency, consistency and retention

- Intake: require `Idempotency-Key` (UUID or 128-bit random browser-generated request token) per actual submit when V9 API is introduced. Store key hash and resulting lead reference server-side with short retention; same key+same payload returns same reference; same key+different payload returns 409. V8 legacy API has its own validation and must not be claimed to support this until implemented.
- Outbox: unique side-effect key, leased worker, consent recheck just before send; may record `unknown_provider_result` internally for uncertain timeouts rather than blindly retrying. Provider limitations must be documented.
- Concurrent owner edits: row version or compare-and-set; old version returns 409 with current state, never silently overwrites another edit.
- Multi-step action: transactional DB write + audit + queued message; commit before external send. No transaction held open during network calls.
- Scope: message content and AI prompts retained only as necessary and for an approved period; respect withdrawal and legal retention (do not assume deleting a lead with an active job is safe). Backup retention and anonymization plan require owner/legal review.

## D. Proposed REST contracts (add after implementation; HTTPS only)

All private endpoints use secure owner/partner PHP session, CSRF on mutations, strict JSON body limits, `application/json`, no CORS wildcard. Consistent envelope:

```json
{"ok":true,"data":{"reference":"ATL-EXAMPLE"},"request_id":"req_example"}
```

Error envelope; use safe codes `VALIDATION_ERROR`, `NOT_AUTHORIZED`, `CONSENT_REQUIRED`, `CONFLICT`, `RATE_LIMITED`, `DEPENDENCY_UNAVAILABLE`, `DISABLED`; never reveal SQL/model/provider stack traces:

```json
{"ok":false,"error":{"code":"VALIDATION_ERROR","message":"Please check the required fields"},"request_id":"req_example"}
```

| Route (planned) | Auth | Request core fields | Success | Side effects / restrictions |
|---|---|---|---|---|
| `POST /api/v9/chat/qualify` | Public, rate-limited | `service_code`, bounded `answers`, session nonce | `questions`/`summary`/`needs_owner_review` | No lead persistence until explicit consent |
| `POST /api/v9/leads` | Public, consent + idempotency | V8 fields + `service_code`, `contact_consent`, `notice_version` | `reference`, `next_step` | Validated DB transaction; **no** marketing opt-in by default |
| `GET /api/v9/leads` | Owner | page/filter | Redacted list as necessary | No partner/public access |
| `PATCH /api/v9/leads/{id}` | Owner + CSRF + version | `status`, owner notes, version | Updated record/version | Audit, optimistic conflict check |
| `POST /api/v9/tasks` | Owner | lead/job, type, due date | task ID | No external send |
| `POST /api/v9/followups/schedule` | Owner | lead, approved template, purpose, due date | outbox ID/status | Consent gate and kill switch |
| `POST /api/v9/messaging/opt-out` | Signed consumer token or owner | channel, purpose, decision | suppression acknowledgement | No token reuse, immediate server suppression |
| `GET /api/v9/opportunities` | Owner | filter | Reviewed candidates | Collector never auto-contacts |
| `POST /api/v9/content/{id}/approve` | Owner + CSRF | version, factual approval | approved draft | Not same as publish |
| `POST /api/v9/campaigns/{id}/approve-budget` | Owner + CSRF + step-up if adopted | amount, currency, start/end, version | audit approval | Does **not** itself spend unless explicitly enabled and verified |
| `GET /api/v9/reports/overview` | Owner | UTC date range | verified + estimated aggregates | No customer PII to browser analytics |
| `POST /api/v9/jobs/{id}/financial-entries` | Owner + CSRF | kind, amount decimal string, reference | immutable ledger entry | Reconcile before marking received |

### Example: future public lead request (synthetic data)

```json
{
  "service_code": "VC",
  "fullName": "Example Customer",
  "email": "example@example.invalid",
  "phone": "",
  "answers": {"room_capacity": "20", "platform": "Teams", "city": "Mumbai"},
  "message": "Request an assessment for a new meeting room",
  "contact_consent": true,
  "marketing_email_opt_in": false,
  "notice_version": "owner-approved-version",
  "source": "guided-chat"
}
```

The existing V8 endpoint is `/api/lead.php` and uses `contactConsent: "yes"`, `service` and other field names. Do **not** send the above V9 payload to V8 without a tested compatibility adapter.

## E. Field validation and permissions

- Approved service codes: `IT_AMC`, `VC`, `AUD_AV`, `SOUND`, `RECOVERY`, `HARDWARE`, `SOFTWARE`, `OTHER`. Map old free-text `leads.service` to these without destructive overwrite; owner can correct.
- Name ≤100, email ≤120 valid syntax, phone ≤20 optional, note ≤2000, answers max 8 keys with per-value ≤400 chars, URL/path bounded, total body e.g. ≤16 KiB; make exact per-endpoint limits test fixtures.
- Region/service area must use allowlist; do not infer geolocation from IP for automatic eligibility. Urgent requests get human escalation.
- Owner permissions: mutate CRM, content approvals, quotes, partners, budgets, finance. Partner permissions: assigned job limited updates. Public: submit after consent, check own generic tracking token only. AI/cron: no owner-action privilege.
- Money: parse string decimals, reject negative invoices/costs (represent reversals via positive reversal kind), overflow, wrong currency and unapproved tax assumptions.
- No sensitive IDs or tokens in URLs other than V8 limited tracking token (already expiring); prefer IDs plus session authorization for all new private routes.

## F. State machines and prohibited transitions

Lead: `new → qualified → responded → converted/closed`, but owner may correct earlier state with an audit reason. No system/AI mark `converted` without a real approved job; document exception reason.

Job: preserve server-enforced V8 transitions. Crucially `assessment`/`quote_pending`/`awaiting_customer_approval` **cannot jump to `in_progress`**; required `approved` contains positive quote + owner-recorded external customer approval evidence. Price change reopens approval. Partners never self-approve, close contracts or change prices.

Message: `queued → leased → accepted → delivered` when provider confirmation exists; `failed`/`suppressed` are terminal unless owner authorizes requeue. Provider `accepted` is **not** delivery proof.

Campaign: `draft → awaiting_approval → approved → active/paused/completed` only with owner-approved budget, verified platform authorization and control flag; V9 initial mode never performs automated transition to active.

Content: `idea → draft → needs_review → approved → published`; `published` only after confirmed publish, with reversal/correction mechanism.

## G. API tests required before rollout

Public validation, consent withdrawal, duplicate idempotency, race/retry, rate limits, malformed JSON, unauthorized role, CSRF missing, XSS strings, injection-like model output, oversized requests, unknown service, finance precision, quote escalation, provider timeout, outbox locking, audit trace and kill-switch OFF. See [test plan](07_TEST_AND_ACCEPTANCE.md).
