# Aaditechs preproduction security & data handling checklist

> No box below should be assumed complete merely because code exists. Execute against Hostinger staging, save evidence privately and obtain owner approval.

- [ ] Confirm site and portal serve HTTPS with valid certificate; HTTP to HTTPS redirect before exposing any forms.
- [ ] Place `secure/aaditechs-config.php`, `vendor/`, rate files and maintenance script **outside** `public_html` and verify direct HTTP requests cannot reach them.
- [ ] Protect production file permissions, disable directory listing and restrict backups/logs; no customer data in repository.
- [ ] Use a database account with only necessary CRUD permissions; import schema into isolated staging database first.
- [ ] Set admin `password_hash()` and unique partner passwords; test weak/incorrect password, throttling, disabled partner session revocation, logout and 30-minute idle expiration.
- [ ] Verify partner cannot view another partner's jobs, view customer email/phone, or update quote/partner cost; admin-only UI and backend authorization.
- [ ] CSRF tokens required on portal POST; verify rejected missing/invalid tokens; session cookies Secure/HttpOnly/SameSite=Strict.
- [ ] Owner verifies each partner's authorization, quote prices and scope before sending customer promises; do not auto-approve a work order.
- [ ] Review privacy notice and establish purpose, retention, contact point, processor agreements and lawfully permitted partner data sharing.
- [ ] Enable `database.enabled`, `leads.enabled` only after testing, then enable *public* `leadSubmission`; use a synthetic test inquiry and confirm record exists once with correct consent and reference.
- [ ] Test invalid origin, malformed JSON, oversized payload, missing consent, honeypot, duplicate/flood and rate cooldown.
- [ ] Test notification delivery on SMTP failure: lead should still be stored, dashboard visible; no false lost lead.
- [ ] Test tracking link lookup with valid, expired/rotated and malformed tokens; verify no customer PII/cost accessible.
- [ ] Consider access logs containing tracking URLs; redact query strings and minimize log retention if supported.
- [ ] Test backup and restore on staging; write rollback procedure for web assets and DB migrations.
- [ ] Confirm actual services, phone numbers, office location, partner scopes, terms, pricing model and warranty disclosures before public launch.
- [ ] Keep Google API feature disabled until official approval and authorized Google OAuth credentials; never commit tokens.


## V8 additional acceptance checks (23 November 2026)

- [ ] Apply fresh V8 DB schema OR the documented one-time V7→V8 migration after a verified backup; never both.
- [ ] Configure private `public_base_url` to the correct HTTPS origin; never trust request Host to construct customer links.
- [ ] Confirm customer tracking links expire at `tracking_ttl_days`, rotate properly, and only display generic case information.
- [ ] Confirm partner cannot move `assessment` to `in_progress` or bypass `awaiting_customer_approval`.
- [ ] Confirm positive quote + owner-entered evidence ID required for `approved`, audit history saved, and change to approved price reopens quote workflow.
- [ ] Confirm partner can add assessment notes without changing status and cannot see customer PII/owner costs.
- [ ] Password reset/disable revokes existing partner sessions; admin password hash change revokes owner sessions.
- [ ] Do not turn on public lead submission until privacy notice has been reviewed and real DB/SMTP fallback behavior validated.
- [ ] Independent pen test/2FA/WAF decision before exposing admin portal to a large customer base.

## V8.2 owner MFA and tracking minimization (not yet activated)

- [x] Optional owner-only RFC 6238 TOTP with validated private base32 secret and unit tests; **disabled by default**.
- [x] Owner session fingerprint incorporates password hash, TOTP enablement and secret, revoking sessions after either changes.
- [x] Anonymous CSRF token is rotated after successful login.
- [x] Bearer-token tracking page no longer renders free-form job title; case code/status/date only.
- [ ] Enroll owner authenticator securely, verify server clock and recovery on **HTTPS staging**, then enable MFA in private config if approved.
- [ ] Add or evaluate second factor for partners according to risk; currently their sign-in is password-only.
- [ ] Complete staging DB/SMTP/auth tests, audit privacy copy and verify backup before public launch.

## V9–V10 growth security addendum (pending live verification)

- [ ] Independent owner/publishing/worker credentials, HTTPS, scoped leases, no secrets logged, rate limits and revocation verified.
- [ ] Local model loopback only; trusted weights, cloud disabled, no customer data in catalog-only job payloads.
- [ ] Media files private, exact manifest review, no publication permission; expert drafts human reviewed.
- [ ] SEO audit read-only, approved metadata changes via review, no public report endpoint or invented metrics.
- [ ] Verify all defaults OFF, backup/restore and staging sign-off before activation.

## V10.1 Meta security gates (pending runtime)

- [ ] Verify least privilege Meta scopes, approved test account IDs and separate tokens, no query secrets/redirects, exact HTTPS identity, scoped revision and receipt ledger.
- [ ] Test ambiguous send reconciliation and stop worker before pause/credential rotation. No automatic retries. Instagram and private media remain unconnected.

## V10.2 network security gates (pending live verification)

- [ ] Verify scoped organization/user/location identity, separate OAuth tokens, bounded HTTPS transport and no redirects, no logs of secrets or raw errors. No automatic retries, only approved revisions, manual uncertain reconciliation. Confirm actual provider visibility and quotas separately.

## V10.2A private media security (pending live)

- [ ] Verify private directory, trusted files, symlink refusal, exact binary hash, immutable metadata and channel binding, provider URL allowlist, no public paths/credentials, orphan file cleanup and retention policy. Never retry uncertain uploads; review actual files and platform policy.

## V10.2B Instagram security (pending live)

- [ ] Direct binary upload only to fixed Meta host. Validate linked Page/IG identity, manifest/byte count, separate public Reel approval, FINISHED gate, no URL exposure, no retries, manually reconcile in-flight/uncertain/expired states. Review media rights and disclosures.

## V10.2C media security (pending live)

- [ ] No public asset URLs, exact in-memory bytes, fixed host, private storage, exclusive revision reservation, explicit Page/type approval, bounded upload, manual uncertain reconciliation. Verify actual file rights, provider specifications and retention.

## V10.2D image security (pending runtime)

- [ ] Exact image hash, size, scoped upload URL, no redirects or token logs, owner/expiry check, public approval, exclusive revision, no blind retries.

## V10.2E bearer media security (pending runtime)

- [ ] 256-bit token digest, 15-minute window, 20 requests, no caching or token logs, exact bytes and current approval, revoke on cancel/publish, no sensitive content. Bearer URLs are accessible to anyone holding them; do not enable unless hosting/CDN log redaction confirmed. Verify rate limits and retention.

- [ ] Keep fetch_log_redaction_verified OFF until actual query log redaction is independently checked. No public endpoint activation by default.

## V10.2F media retention security (pending runtime)

- [ ] Owner session only, no public preview, exact content-addressed name and hash, no symlinks/hardlinks, bounded bytes, all references protected, no deletion, paused explicit CLI only. Review actual backups, audit recovery, storage quota and retention policy.

## V10.2G OAuth security (pending runtime)

- [ ] Private token config 0600, no secrets in DB/logs/Telegram/Git, mandatory lifecycle enforcement at release, fail closed on missing/expired/revoked metadata, manual paused rotation and actual provider revocation. Identity health is not scope or publish proof.

## V10.2H media reconciliation security

- [ ] Persist only validated intermediate resource ID, no signed URL/token or raw response. No automatic retry; inspect uncertain rows and reconcile provider-side. Test audit failure and worker interruption.

## V10.2H.1 security (pending runtime)

- [ ] Private exact hash, exclusive revision, explicit public approval, pinned signed URL without OAuth bearer, bounded multipart, persistent URN and Reel container, no secret logging or blind retries. Verify actual host quotas and provider rights.

## V10.2H.2 security (pending runtime)

- [ ] Private exact video hash, exclusive text/image/video reservation, separate explicit public approval, no secrets in receipts or reports, X bounded fixed-host upload, fail closed on processing/expiry, Threads short-lived bearer (any holder can fetch), mandatory verified log redaction, cap and revocation, no sensitive assets. Check actual memory/rate limits, stop workers before emergency pause, no uncertain replay.

## V10.2H.3 security (pending runtime)

- [ ] Exact ordered bundle approval, image-only 2–5 cap, independent high-entropy bearer digest per child, verified log redaction/no cache, pause and current manifest checks, persist child evidence before next write, revoke on parent/cancel/failure, no raw tokens in reports. Single-range video only after full verified read, request cap retained. Private inbox separate from webroot and storage, exact hashes, no credential persistence, source files retained. No automatic retry or real posting.

## V10.2I/J security (pending runtime)

- [ ] Keep transfer config, SSH key, known_hosts and local artifacts private, verify host fingerprint out of band, dedicated least-privilege SFTP inbox user and no password/agent/proxy, no automatic retry or deletion. Confirm server path cannot expose webroot and permissions/quotas enforced. OAuth alerts contain only sanitized local deadline/health, not secrets or provider responses. Keep live features OFF until accepted.

## V10.2K/V10.3 security (pending runtime)

- [ ] Separate Telegram business bot and owner bot, bounded fixed-host HTTPS multipart without redirects/proxy, exact channel verification, explicit approval, no automatic retry, no raw tokens or response logging. Communications: aggregate counts only, explicit authorized import, no personal identifiers, contents, recordings, credential harvesting or outreach. Verify lawful source authorization and retention before activation.

## V10.3A/V10.4 security (pending runtime)

- [ ] Metadata-only Gmail scope with dedicated authorized business account, fixed HTTPS GET endpoints, no message lists/bodies/headers/IDs, no credentials or mailbox address in DB/reports, short local expiry gate, generation change, latest count snapshot only. Verify OAuth consent and restricted-scope policy before activation. Local discovery only from explicitly authorized sources, no scraping/contact harvesting, bounded batch, exact validation, no automatic outreach; reminders internal only and owner controlled.

## V10.5 security (pending runtime)

- [ ] Independent default-OFF dashboard flag, portal owner role only, existing password/TOTP session fingerprint, expiry, HTTPS, GET only, strict period allowlist, server-side escaping, private headers and no external scripts. No personal contact fields, message bodies, drafts, source URLs, raw receipts/tokens or write actions. Confirm staging access controls and performance with realistic sanitized data before activation.

## V10.5A/V10.6A security (pending runtime)

- [ ] Owner-only HTTPS CSV, separate OFF flag, bounded aggregate columns, formula escaping, no customer/session IDs, no messages or credential fields, private download storage and retention review. Communication batch CLI only with explicit source authorization, independent flag, bounded payload and atomic rollback. Release checker read-only, no raw credentials/errors, no database changes. Migration replay uses synthetic loopback CI database only; never run destructive test reset on live/staging data. Verify actual backup and restore separately.

## V10.6B security (pending runtime)

- [ ] Private authorized OAuth client/refresh secret, fixed HTTPS token endpoint, no redirects/proxies/raw response output, exact scope/identity checks, no active config overwrite, exclusive candidate 0600 in private 0700 root. Candidate contains a live access token if actually issued; do not share or commit, inspect and dispose privately after rotation. Retention independently OFF, paused workers, verified backups, exact plan hash and transaction, no scheduled cleanup or audit/publication deletion.

## V10.6C security (pending runtime)

- [ ] Independent default-OFF bridge and per-channel authorization, fixed HTTPS URL, no arbitrary URL input, public IPv4 pinning/no redirects/proxy, bounded response, private bearer and independent HMAC key, exact signed payload and generation/freshness checks, no raw data/secret output, immutable aggregates. Source Git blob SHA-1 checks detect drift, not signed release authenticity. Release DB checks are read-only; unresolved evidence blocks and is never automatically modified. Verify actual process shutdown, grants, backup/restore and provider acceptance separately.

## V10.6D security (pending runtime)

- [ ] Review CLI separately OFF; owner-authorized operator, stop workers, pause, independently review provider evidence (store securely outside Git), exact state/evidence hashes, append-only review with no publication mutation or replay. Review is an attestation, not automated provider verification; unresolved evidence must remain blocked. Backup dump restricted private file/directory, independent expected SHA256, bounded streaming decompression and no SQL execution; encrypt and protect backup at rest separately. Candidate inspection does not expose token; exact digest, current mailbox and expiry rechecked; manual activation only, scope verification remains mandatory.

## V10.6E security (pending runtime)

- [ ] Native provider flags independently OFF; private restricted credentials, fixed HTTPS GET hosts and bounded responses, no redirect/proxy, exact account and period, no raw response or credential logs. Store only latest per-source aggregate snapshot and account hash/generation; no contact records, message bodies or recordings. Confirm upstream fields and retention separately. Disposable restore test must never run against real data; inspect actual encrypted backup and isolated restore separately.

## V10.6F security (pending runtime)

- [ ] Separate album flag, exact private PNG/MP4 bytes, one provider request only after distinct approval, validated group and message receipts, no retry of unknown rows. WhatsApp webhook separately OFF with explicit authorization and verified log redaction; verify raw HMAC before DB, exact app/account/phone and bounded payload. Raw webhook may transiently contain personal data; disable payload and query logging, protect verify token and stable HMAC dedupe key, treat keyed digest as pseudonymous, approve retention before enabling. No automatic delete, inbound content store, response or follow-up send.

## V10.6G security (pending runtime)

- [ ] Separate OFF flags, trusted static callback URL, no forwarded host trust, Twilio HMAC-SHA1 per provider protocol, strict form parsing/body cap, dedicated account/destination and stable keyed event digest. Raw callbacks may transiently contain customer details; redact bodies and secrets across logs, rate-limit upstream, no automatic replies/routing or recording. Twilio auth token is sensitive; dedicated subaccount recommended. Replay beyond dedupe lifetime is not solved; no Twilio cleanup enabled. WhatsApp cleanup requires approved 30-day policy, verified backup, paused workers and exact plan digest. No actual deletion or deployment.

## V10.6H security (pending runtime)

- [ ] Mixed media OFF separately per provider; private exact manifest MP4, typed ordered digest, current approval and target, limited bearer lifetime and fetch count, no logs or automatic retry. Communication reviews OFF; no personal identifiers, no inferred unanswered counts, no outbound follow-up, retention blocks open reviews and cascades closed reviews when approved aggregate cleanup executes. Audit retained. No deployment.

## V10.6I security (pending runtime)

- [ ] Search Console independently OFF, authorized dedicated webmasters.readonly grant, fixed Google HTTPS endpoints, bounded response and no proxy/redirect; reject broader/missing scope and unexpected refresh rotation. Exact property allowlist and returned property verification, no user-supplied destination or query terms; in-memory access token only, private refresh credentials, generation/expiry/revocation gate. Snapshot contains only aggregate counts/position/date coverage and property hash; no active config overwrite or automated schedule. Actual consent, secret storage and provider acceptance required.

## V10.6J security (pending runtime)

- [ ] Separate history and live-import OFF gates, exact property and generation binding, global bounded history cap; no keyword or raw payload storage. Local crawler explicit operator authorization, fixed HTTPS host/path and public IPv4 pin, no redirects/proxy/credentials, conservative reviewed robots match, body cap and request pacing. No private routes or link discovery. Report is operator-supplied evidence, not signed remote attestation; import checks coverage/freshness and rejects older/duplicate capture. No scheduling or automatic deletion. Approve retention and actual network/hosting acceptance first.

## V10.6K security (pending runtime)

- [ ] Local diagnostic fixed loopback only, no redirects or proxy, exact digest and bounded response, synthetic prompt only; tag/digest checks are not cryptographic execution attestation or proof Ollama cannot use network. Enforce trusted offline runtime and egress isolation separately. Private SEO cleanup independently OFF, paused workers, backup/restore, exact plan and transaction; no automatic deletion. GBP read check independently OFF with existing target/OAuth gate, fixed read-only media path and no response/credential output, never infer write permission. No actual activation or deployment.

## V10.6L security (pending runtime)

- [ ] Discovery cadence/recovery separately OFF, existing fixed HTTPS public-IP feed controls unchanged, growth-control lock for admission and each import, unresolved run blocks new fetch, recovered old run cannot continue importing. Explicit stopped-worker attestation and exact state digest before marking interrupted ledger failed; no opportunity mutation or automatic retry. No cron or real provider access. Local batch savepoint prevents partial writes when caller catches errors. Actual source consent and scheduling approval required.

## V10.6M security (pending runtime)

- [ ] Batch creation independently OFF; private CLI, exact matrix digest, bounded 126 requests per objective, pause/transaction lock, no auto approval or publishing. Twilio retention OFF; paused exact plan, hash-only replay table, no raw callbacks/contact/message data. Stable secret key and target namespace required for replay protection; no automatic key rotation, replay hash deletion or infinite-cap claim. Confirm real retention approval and isolated restore. Release check uses shared ledgers and rejects empty/negative/noninteger state summaries. No production changes.

## V10.6N security (pending runtime)

- [ ] Verify limit-bound v2 plans, pause and feature flags, UTF-8/catalog refusals and result collision rollback in synthetic CI and isolated staging. No old v1 plan reuse, auto approval or publication. No actual provider calls or production changes.

## V10.6O security (pending runtime)

- [ ] Verify default-off scheduler, cadence and authorization prerequisites, private log/cron permissions, no network in configuration check, read-only status, pause and overlap guards. Verify UTF-8 refusals and atomic intake/draft review writes. Actual provider acceptance and scheduler installation remain pending.

## V10.6P security (pending runtime)

- [ ] Confirm existing owner-only session/HTTPS/CSV gates, no personal data or draft text in new sections, fixed SQL dimension allowlist, disabled module behavior and missing vs zero. No new collection or provider access.

## V10.6Q security (pending runtime)

- [ ] Verify independently pinned model and restricted Ollama egress, exact local catalog, strict JSON, bounded sequential polling and stop on uncertain outcome; no overlapping schedulers, secrets or customer context. Inventory is not attestation. Actual runtime acceptance pending.

## V10.6R security (pending runtime)

- [ ] Verify independent reference provenance, private permissions, exact contract and digest, no raw metadata in comparison output, canonical backup path, real isolated restoration and worker shutdown. Metadata parity alone is not release approval.

## V10.6S security (pending runtime)

- [ ] Verify private canonical job/output paths, actual ACLs, exact catalog and digest, bounded batch, no transfer/publication, strict manifest, codec validation and cleanup on failure. Review actual imagery, model license and local egress. No native video acceptance asserted.

## V10.6T security (pending runtime)

- [ ] Verify independent index digest and individual server approvals, canonical private paths, host key/account/inbox authorization, full local codec checks, no connection by default, no automatic retry on uncertain delivery. Actual server import and permissions still required.

## V10.6U security (pending runtime)

- [ ] Verify batch flag OFF by default, current individual approvals, private sibling roots, independent digest, all-item preflight, audit rollback and partial-file recovery. No automatic publication or cleanup. Actual hosting acceptance pending.

## V10.6V security (pending runtime)

- [ ] Validate aggregate reverse audit classifications, sample truncation, memory/read bounds and private outputs; stop workers and verify backup and policy before paused quarantine planning. No automated removal or bulk apply. Actual retention acceptance pending.

## V10.6W security (pending runtime)

- [ ] Confirm explicit boolean revocation metadata, independent batch flag, private plan digest, stopped workers/paused growth, actual provider consent and no secret output. No auto-refresh/revocation or publish. See V106W_OAUTH_HEALTH_BATCH.md.


## V10.6X — reviewed opportunity follow-up batches (source only)
Private OFF-by-default 1–50 reminder set/reschedule/complete/cancel batches, read-only exact-state plan, digest-bound atomic apply, per-item audit, single-action audit rollback and 73 scope + 84 service regression cases. No external outreach or production changes. See [V10.6X](V106X_REVIEWED_FOLLOWUP_BATCH.md) for operator gates; CI and actual staging pending.


## V10.6Y — reviewed communications and retention integrity (source only)
New default-OFF private 1–50 aggregate review batch across email, WhatsApp, SMS and calls; exact-state plan/apply, per-item audit, local rollback and full-state retention digest. 108 scope and 24 channel scenarios authored, plus retention drift tests. Prior V10.6X CI passed. New CI and actual hosting/consent/retention acceptance pending. See [V10.6Y](V106Y_COMMUNICATION_REVIEW_BATCH.md).


## V10.6Z — private page-level SEO diagnostics (source only)
Optional detailed seven-page observation v2 with strict path/status/finding consistency, aggregate-only persistence, v1 compatibility, atomic Search Console snapshot/history/audit and live SEO audit rollback. 56 crawler + 126 report validation scenarios authored. Prior V10.6Y CI passed; new CI and actual crawl/provider/staging acceptance pending. See [V10.6Z](V106Z_PAGE_SEO_DIAGNOSTICS.md).


## V10.7A — competitor editorial evidence (source only)
Offline private operator-supplied own/peer comparison across 14 services and six editorial signals (84 cells), 1–5 peers, explicit unknown evidence, source references and review-only gap suggestions, 504 synthetic decision cases. No scraping, actual competitor claims, rankings, publishing or database changes. Prior V10.6Z CI passed; new CI and real evidence acceptance pending. See [V10.7A](V107A_COMPETITOR_EVIDENCE.md).
