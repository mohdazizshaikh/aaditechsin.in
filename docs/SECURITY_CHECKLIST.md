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
