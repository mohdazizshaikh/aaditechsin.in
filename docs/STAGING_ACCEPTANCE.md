# Aaditechs V8+ — Hostinger staging acceptance (not yet deployed)

**Status at 23 Nov 2026:** The `main` source and all local/offline tests were verified; the Hostinger account was **not authenticated/accessible in the current assistant session**. Nothing was uploaded to Hostinger or configured in production. The `source-validation.yml` GitHub Action runs source checks only; green CI is **not** hosting acceptance.

## Staging prerequisites

1. Connect the Hostinger Connector to this ChatGPT/Codex environment using its official MCP integration (`https://mcp.hostinger.com`). Do not paste passwords or tokens into chat. Confirm the exact Hostinger **website and account** before any change. If the target staging site has existing content, obtain explicit owner approval and a verified backup before an overwrite.
2. Provision or identify a **separate, HTTPS staging domain/subdomain** for V8 tests. Restrict discovery by password/access controls and `X-Robots-Tag: noindex`; do not rely only on robots.txt. Do not change production DNS or overwrite `aaditechs.in`.
3. Verify PHP >= 8.1, `pdo_mysql`, `curl`, `openssl`, `json`, HTTPS redirect, private directory **outside** webroot, database support, SMTP capability, and backups. The reference file layout in `README_DEPLOYMENT.md` is illustrative until the actual Hostinger website path is confirmed.
4. Deploy only the contents of `public_html/` to staging webroot. Put `secure-setup/aaditechs-config.php.example` in staging's private `<domain-root>/secure/aaditechs-config.php`, replacing placeholders privately. Put `secure-setup/maintenance/preflight.php` in `<domain-root>/secure/maintenance/preflight.php`. Keep everything else in `secure-setup/`, docs and tests outside the webroot. Do not commit private config.
5. In staging private config, set `public_base_url` to the **actual HTTPS staging origin**; use test-only owner/partner credentials, SMTP test inbox and a dedicated staging database. **All feature flags OFF initially.**

## Read-only preflight on staging

From the hosting shell (or private CLI console), run:

```bash
php /path/to/domain-root/secure/maintenance/preflight.php
# After separate staging DB has been created/imported and credentials are stored privately:
php /path/to/domain-root/secure/maintenance/preflight.php --check-db
```

The preflight tests runtime, safe private config placement, flag states and (with `--check-db`) **read-only SELECT/schema checks**. It deliberately does **not** send email, write any lead, apply migrations, prove HTTPS externally or disclose credential values. A missing prerequisite is a BLOCKER rather than a false success. The bundled offline/CI check is:

```bash
php secure-setup/maintenance/preflight.php --source-only
```

## Test execution / acceptance evidence

| Test | Exact acceptance criterion | Evidence to record (no secrets) |
|---|---|---|
| Public pages | All service pages and original logo load at HTTPS staging | Status code and screenshots |
| Security boundary | `/secure/`, `.env`, SQL, backup and config inaccessible externally; restricted staging indexing | 403/404 results, response headers |
| Database | Fresh staging `schema.sql`, or backed-up V7 DB with V8 migration exactly once; 4 tables | Preflight pass and applied migration ID |
| Lead consent | Without consent / invalid payload / rate-limit fails; valid synthetic lead inserts exactly once | Test case ID, DB row count |
| Admin access | CSRF rejects, wrong password rejects, login/out works, password change invalidates old session; owner TOTP setup, fresh code, stale code and loss/recovery drill if MFA enabled | Redacted test log |
| Partner access | Unauthorized cases and PII inaccessible; disabled partner loses access; cannot start work pre-approval | Redacted test case transitions |
| Quote / approval | Positive approved quote plus minimal external evidence method/reference required; audit event present | Synthetic case ID and event type |
| Customer link | Minimal status, no PII or prices; expiry and rotation invalidate link | Status codes, **not tokens** |
| SMTP | Test message accepted **and delivered** to controlled inbox; DB lead survives SMTP failure | Redacted delivery log |
| Google Reviews | Disabled pending Google API approval | 503 and feature flags OFF |
| Recovery | Backup and restore test, restore timing recorded, production untouched | Timestamp, checksum |

The business owner must review the still-draft `/privacy/` content and authorize appropriate partner-data sharing and customer-data retention before enabling public submissions. Never deploy real customer credentials or live business leads to staging.

## Release gating

Do **not** enable `database.enabled`, `leads.enabled`, `portal.enabled` or public `leadSubmission` until relevant staging tests pass; keep `google.enabled` / `googleReviewsApi` OFF until approved. Do **not** deploy or overwrite production without a verified backup and explicit approval for the exact live target.

Before production: explicitly confirm whether owner TOTP is enabled (default OFF). If enabled, check server clock, fresh/stale OTP behavior and an offline recovery drill without exposing the private secret. Partner MFA remains an open requirement for higher-assurance partner access.

## Additional V8.3 evidence source

The GitHub Actions `disposable-mariadb-integration` job provides reproducible **synthetic test-only** MariaDB/PHP behavior and owner/partner transition evidence. The job uses a disposable self-signed localhost TLS proxy plus `tests/ci_https_router.php` to **simulate hosting TLS termination**: this is NOT a real TLS/SSL test. It cannot replace the separate Hostinger staging acceptance items above. No CI test password is a production credential, and the ignored `secure/aaditechs-config.php` generated during CI must never be uploaded or committed.


## V8.4 queued SMTP release gate (additional to existing tests)

- [ ] Apply the V8.4 `notification_outbox` migration to a backed-up staging database; run read-only `preflight.php --check-db`.
- [ ] **Before enabling `mail.enabled=true`**, install PHPMailer privately and configure a test SMTP recipient privately. No send occurs from a public API request.
- [ ] Submit a synthetic lead, confirm exactly one outbox row and read-only worker statistics.
- [ ] Explicitly run the private worker using `--run` and verify test inbox **delivery**, not just SMTP acceptance.
- [ ] Test SMTP downtime: lead persists and outbox retries with backoff; review `dead` alerts and authenticated owner-only retry.
- [ ] Schedule private cron execution only after testing; monitor backlog and confirm duplicate alerts can be de-duplicated by lead reference.
- [ ] Confirm backup/restore contains leads **and** notification_outbox, plus private runtime config recovery; do not include secrets in backups made public.

## V8.5 retention-safety gate (not deployed)

- [ ] Set an approved integer `retention_days` in private config; no automatic deletion until retention and legal policy sign-off.
- [ ] Run `php secure/maintenance/prune.php` (or `--dry-run`) and verify it prints only an eligible count without database writes.
- [ ] With synthetic staging rows, confirm jobs and pending/sending/dead alerts prevent deletion. Verify one expired unassigned lead with a sent alert is eligible.
- [ ] Record a verified staging DB backup before any `--run`. Confirm the exact count changed and backups permit recovery.
- [ ] Review any existing Hostinger cron: previous no-argument `prune.php` invocations are now read-only. Never silently convert those schedules to `--run`.

## V9–V10 additional release gates (all pending live acceptance)

- [ ] Resolve stacked PRs in dependency order and verify exact release SHA and CI. Back up staging and apply all missing migrations sequentially through V10.0; fresh DB uses full schema. Test restore.
- [ ] Owner-only Telegram authorization/replay, analytics consent, authorized discovery, growth pause, publication test destination and notification receipts.
- [ ] V9.7 TLS, worker token rotation, rate limiting, expired leases and actual local Ollama runtime.
- [ ] V9.8 actual private media render, matching manifest and owner review; no binary uploads or native AI video assumed.
- [ ] V9.9 static guide browser acceptance, local expert output safety and human review; no public live AI.
- [ ] V10.0 run source audit, review findings, register synthetic summary, test /seo and /seotopics. Search Console and live SEO acceptance separate.
- [ ] Confirm all features default OFF, local credentials private and no unintended publishing or outreach. Separate owner decision for production.

## V10.1 additional acceptance (pending)

- [ ] Apply V10.1 after V10.0 in release order. Verify official Meta app access, exact scopes, API versions, approved test identities and private token storage.
- [ ] Test target mismatch, revoked token, pause, approved revision, cancellation, ambiguous response and reconciliation before scheduling any authorized test publication. Confirm actual post independently. No Instagram/media acceptance implied.

## V10.2 additional acceptance (pending)

- [ ] Apply V10.2 after V10.1; verify LinkedIn administrator role/version, X user-context scopes/quota, GBP eligibility and account/location authorization. Keep all flags OFF until approved test destination.
- [ ] Verify real post receipt and visibility independently, ambiguous reconciliation and unified historical ledger counts. No YouTube/Instagram acceptance implied.

## V10.2A acceptance (pending runtime)

- [ ] Verify authenticated SFTP, private storage ACL, 20 MiB/memory cap, hash and stale manifest rejection, exact metadata/visibility/account approval, cancellation and no automatic retries. Confirm channel and test video processing/visibility independently; no Instagram media gate accepted.

## V10.2B acceptance (pending runtime)

- [ ] Apply V10.2B after V10.2A. Verify Meta Page/linked professional account, version, roles/scopes, real video format and disclosure, private transfer and processing status. Test mismatch, cancellation, expired container, ambiguous upload/publish, provider receipts and actual visibility separately. Explicit owner authorization for public test Reel required.

## V10.2C acceptance (pending runtime)

- [ ] Apply after V10.2B; verify Page role/scope/version, local media decoding, host size/memory, private hash/profile checks, text/media exclusion, wrong type/target, no uncertain retry, actual resource processing and visibility. Explicit public test authorization required.

## V10.2D acceptance (pending runtime)

- [ ] Apply after V10.2C. Verify LinkedIn admin and AVAILABLE owner, X user context and expiry, hash/target/text-image exclusion, uncertain reconciliation and real visibility. No video acceptance.

## V10.2E acceptance (pending runtime)

- [ ] Apply after V10.2D. Verify actual HTTPS positive/negative GET/HEAD, expiry, fetch cap, revocation/pause, log redaction and no caching, private JPEG format/crop, real Meta container readiness/visibility. Explicit public authorization. No carousel/video acceptance.

- [ ] V10.2E requires explicit fetch_log_redaction_verified=true only after real hosting/CDN log review. Verify Threads 1080x1080 and Instagram 1080x1350 JPEG; synthetic core fetch tests do not prove HTTP deployment.

## V10.2F acceptance (pending runtime)

- [ ] Apply after V10.2E. Verify real admin vs partner/anonymous access, password fingerprint/timeout, no-cache preview, file ACL/hash, bounded inventory, paused old-orphan quarantine and restore, reference protection and DB/filesystem audit recovery. No permanent purge.

## V10.2G acceptance (pending live)

- [ ] Apply after V10.2F. Enable lifecycle enforcement before live OAuth activation; test expired, revoked, malformed/missing metadata and rotation, sanitized read-only checks, stale evidence and actual provider scopes/renewal/revocation. No automated consent/refresh acceptance.

## V10.2H acceptance (pending runtime)

- [ ] Apply after V10.2G; verify persistent image resource receipt before post, audit failure blocks next provider action, crash reconciliation, no replay or token leaks. Actual video/carousel work pending.

## V10.2H.1 acceptance (pending runtime)

- [ ] Verify LinkedIn video format/rights and scopes, exact org, ranges/ETags, receipt persistence failure, separate AVAILABLE gate and actual visibility; Instagram receipt before binary, unified uncertain report, text/image/video exclusivity and storage protection. No automatic retry or other video/carousel acceptance.

## V10.2H.2 acceptance (pending runtime)

- [ ] Apply after V10.2H.1. Verify existing Meta image rows remain images, X scope and exact user, ordered chunks, durable ID before append, finalize/processing/expiry, exact public approval and no duplicate, Threads video type/hash and HTTPS GET/HEAD delivery, pause/cap/expiry/revocation, no caching/log leakage, container FINISHED and provider visibility. Validate real format and quotas, HTTP Range behavior, crash reconciliation and storage references. No carousel or partial-upload resume acceptance.

## V10.2H.3 acceptance (pending runtime)

- [ ] Apply after V10.2H.2. Verify 2–5 ordered JPEGs, wrong bundle and account rejection, duplicate reservations, child receipt persistence, parent and final readiness, no blind retry, child bearer expiry/cap/revocation, storage references, actual single-range 206/416 and memory limits, separate private inbox import and tampered pair rejection. Verify actual provider behavior and independent visibility. Mixed-video carousels and remote SFTP automation remain pending.

## V10.2I/J acceptance (pending runtime)

- [ ] Test dedicated SFTP account and pinned host key mismatch, permissions, exact remote asset pair and import hash checks, timeout/partial transfer recovery, destination rename behavior, no unintended publish. Verify owner-only daily expiry/stale/revoked summary, opt-out and period deduplication, all-ledger counts, actual schedule and delivery. Verify manual rotation and actual provider consent; automatic refresh remains pending.

## V10.2K/V10.3 acceptance (pending runtime)

- [ ] Verify Telegram business bot/channel identity, administrator post permission, real PNG/MP4 constraints, exact type/manifest/account approval, duplicate prevention, private file hash, receipt and independent visibility. Verify communications completed-day counts, extra-field rejection, conflict protection, missing data vs zero, pause, owner reports and retention policy. No live communications connectors, message bodies, call recordings or GBP media acceptance.

## V10.3A/V10.4 acceptance (pending runtime)

- [ ] Verify dedicated business Gmail consent and metadata scope, exact mailbox profile, INBOX system label counts, missing/expired/revoked token and generation change, stale snapshot, no raw responses, paused capture and actual owner report. Verify authorized discovery export source host, exact batch schema, all-or-none rollback, deduplication, source-run visibility, qualified-only reminder, date bounds, pause, completion/cancellation and no outreach. Browser dashboard, live WhatsApp/SMS/telephony and broader opportunity provider acceptance pending.

## V10.5 acceptance (pending runtime)

- [ ] Verify authorized owner HTTPS access, guest/partner rejection, session inactivity and fingerprint rotation, disabled flag, invalid dates/POST rejection, no-store/noindex/CSP, no customer fields or credentials, 7/30 completed UTC windows and current-status cohort labels, missing vs zero communications coverage, all-ledger and OAuth caveats, mobile table readability and DB/query performance on staging. Verify actual data sources separately. No dashboard mutation or release approval inferred.

## V10.5A/V10.6A acceptance (pending runtime)

- [ ] Verify prior schema baseline and missing migration order using release contract; encrypted/controlled backup and restore rehearsal in isolation; stop workers and pause growth before changes. Run read-only release-check.php --check-db; independently inspect types/indexes/FKs, data integrity, schema engine compatibility and privileges. Verify consented IST activity vs UTC aggregates, 7/30 windows, missing data, CSV owner-only and private headers, no sensitive values or spreadsheet formula injection, authorized communication batch dedupe/conflict rollback. Actual provider connectors, live credentials and production release remain pending.

## V10.6B acceptance (pending runtime)

- [ ] Verify Gmail consent and refresh grant, exact metadata scope, mailbox identity, expiry safety margin, unexpected refresh-token rotation refusal, paused growth, exclusive private candidate storage and manual activation/recovery. Verify approved 90-day communications and 30-day stale snapshot retention, dry-run no writes, changed digest rejection, cutoff boundary, transactional rollback, backup/restore and no unrelated deletion. Actual provider and hosting acceptance pending.

## V10.6C acceptance (pending runtime)

- [ ] Authorize aggregate source for each channel, confirm source count definitions and consent, TLS pinned public destination, separate bearer/HMAC secrets, exact signature/generation/freshness/channel/day and no personal data, idempotent import and conflicts/pause. Verify actual upstream integration separately. Compare release migration/fixture file integrity, confirm main baseline, stop workers, pause, reconcile unresolved worker/provider/lease/bearer rows, verify backup and isolated restore, review full cumulative candidate. No deployment or native provider acceptance inferred.

## V10.6D acceptance (pending runtime)

- [ ] Apply new review ledger migration in order after verified backup and isolated restoration. Check exact unknown row, independent provider evidence, state/evidence hash, pause and changed-state rejection, immutable review and unchanged publication status, release block for unreviewed/in-flight/processing rows. Verify private gzip artifact SHA256/CRC and actual restore/schema/data integrity independently. Verify Gmail candidate private permissions, expected digest, expiry, mailbox revalidation and manual rotation; no scope inference from identity. No live acceptance or release authorization inferred.

## V10.6E acceptance (pending runtime)

- [ ] Verify approved WABA account analytics permission and exact UTC window, sent/delivered semantics and actual response shape; authorized restricted Twilio usage-read key, exact account/category/unit/date, pagination refusal, missing versus zero and reporting lag. Confirm local expiry/generation and pause gates, no personal details stored, usage separate from answered/missed counts. Verify actual authorized isolated backup restore and schema/data integrity, not just synthetic CI. No live connector, inbound webhook, reply or release acceptance inferred.

## V10.6F acceptance (pending runtime)

- [ ] Verify actual two-item Telegram album format, exact digest/order/caption/channel approval, exclusive revision, provider group/two message IDs, memory limit and independent visibility. Verify Meta HTTPS subscription challenge and raw-body HMAC, exact WABA/phone, valid signature and tampering, duplicate deliveries, old/future events, pause/retry behavior, bounded body and storage cap, no raw content in hosting/CDN/APM logs, authorized retention and key-rotation procedure. No complete inbox coverage, native SMS/telephony webhooks or automatic replies inferred.

## V10.6G acceptance (pending runtime)

- [ ] Verify dedicated Twilio account/destination, authentic signed form and exact callback URL, URL/encoding variants and duplicate-key refusal, SMS-only and terminal inbound voice status filters, empty SMS response and voice 204 without routing change, duplicates across UTC receipt days, pause, actual delivery/retry and log redaction. Approve replay/retention policy before enabling; classic forms lack signed freshness. Verify WhatsApp 30-day cutoff, bounded dry-run/changed plan/rollback, backup and no Twilio deletion. Actual provider and staging acceptance pending.

## V10.6H acceptance (pending runtime)

- [ ] Verify old JPEG-only bundle approvals survive schema migration; new typed bundle requires one approved MP4, distinct assets, exact account/order and independent mixed flag. Verify actual Instagram/Threads child API behavior, codecs/aspect/duration and complete video fetch before revocation, parent readiness and receipt. Verify communication review reminders only from imported missed counts, owner status transitions, pause and retention guard. Actual provider access, backup/restore and staging release remain pending.
