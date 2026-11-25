# Aaditechs — Hostinger deployment, secrets and security runbook

**Updated:** 23 November 2026 · **GitHub source:** `mohdazizshaikh/aaditechsin.in` → `main` · **Domain:** `aaditechs.in`.

> **Current status:** V8.5 source is implemented; latest verified GitHub CI includes disposable MariaDB tests and offline checks. **No production overwrite, Hostinger staging acceptance, live MySQL or SMTP verification, Google API approval, legal review or customer production traffic are claimed.** The pre-existing private ChatGPT Site preview may still be on an older version. All live lead/portal/Google flags start **OFF**.

## V9–V10 development addendum (24 November 2026)

Main V9.6 verified; V9.7–V10.5A/V10.6A stacked development branches are not deployed. Use [PROJECT_STATUS](docs/PROJECT_STATUS.md) for latest evidence. Review all missing additive migrations in release order (V8, V8.4, V9.1–V9.9, V10.0 then V10.1 then V10.2 then V10.2A then V10.2B then V10.2C then V10.2D then V10.2E then V10.2F then V10.2G then V10.2H then V10.2H.1 then V10.2H.2 then V10.2H.3 then V10.2K/V10.3 then V10.3A/V10.4; V10.2I/J and V10.5/V10.5A/V10.6A have no new SQL migration; do not use lexical filename order); never blindly reimport a live schema. Fresh isolated database uses full schema.sql. Keep all growth/analytics/discovery/creative/creative_transport/media/expert/seo/social/owner-report flags OFF until individual acceptance. Private CLI scripts go in secure/maintenance, local Python workers on the trusted PC, no models or generated assets in public_html. Follow module runbooks and staging gates. SEO is source-only and has no authorized Search Console connection yet.

## 1 — Deployment model and file destinations

The project is a PHP + MySQL/MariaDB app with static frontend. No WordPress or Node production runtime is required. Confirm the Hostinger plan provides PHP 8.1+, PDO MySQL, SSL, a database and private sibling directory. This layout is an example; check the real account path before uploading.

```text
<domain-root>/
  public_html/                  <-- copy CONTENTS of repository public_html/ here
    index.html
    assets/                     <-- logo, stylesheets, scripts and images
    data-recovery/              <-- dedicated service page
    it-amc/                     <-- dedicated service page
    video-conferencing-solutions/
    auditorium-av-solutions/
    professional-sound-systems/
    av-project-planner/
    privacy/                    <-- draft notice, do not enable leads until approved
    api/{bootstrap,workflow,operations,lead,google-reviews}.php
    portal/index.php
    track/index.php
    .htaccess,robots.txt,sitemap.xml
  secure/                       <-- MUST be OUTSIDE public_html, NOT from Git
    aaditechs-config.php        <-- secrets, hashes, server flags
    vendor/                     <-- PHPMailer, optional SMTP
    cache/, rate/, login-attempts/
    maintenance/prune.php       <-- CLI-only script (opt-in)
```

Do **not** upload `secure-setup/` as a publicly accessible subfolder. It contains templates/schema only. Set directory/file permissions as restrictive as supported (typically 0700 for `secure`, 0600 for private config). Check that `https://<host>/secure/aaditechs-config.php` returns 404/403, NEVER content.

## 2 — Secrets inventory and exact configuration

Start from `secure-setup/aaditechs-config.php.example`, copy it **privately** to `<domain-root>/secure/aaditechs-config.php`. Replace placeholders only on the server, not inside source, Git, screenshot or chat.

| Private value | Obtain from | Set in `secure/aaditechs-config.php` |
|---|---|---|
| Database host/name/username/password | Hostinger hPanel → Databases → MySQL Databases | `database.*` |
| Administrator email and password hash | Verified owner; hash generated privately in trusted PHP CLI | `portal.admin_email`, `portal.admin_password_hash` |
| Random 32+ character anti-spam secret | Cryptographically secure local generator | `rate_limit_salt` |
| Public HTTPS origin (no path, no query) | Real staging/production host | `public_base_url` |
| Customer tracking link lifetime (7–365 days) | Owner policy; default 90 | `tracking_ttl_days` |
| SMTP credentials and destination inbox | Hostinger email or approved SMTP provider | `mail.*` |
| Google OAuth Client ID, secret, refresh token | Approved Google Cloud OAuth configuration | `google.*` |
| Google Business account & location IDs | Authorized Google Business Profile API discovery | `google.account_id`, `google.location_id` |

For password hashing, **on a trusted server** run `php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'`, enter the password at the prompt, and store only the resulting hash in the private PHP config. Use a long unique passphrase; do not put the plaintext in a shell command argument or a repo. Auth remains password-only in this V8 release; adding 2FA / external IdP is a recommended hardening milestone.

To install optional email dependency: put `secure-setup/composer.json` in `secure/composer.json`, run `composer install --no-dev --optimize-autoloader` **inside the secure folder** if supported, and verify `secure/vendor/autoload.php` exists. If Composer is unavailable, have the dependency installed via a trusted offline method without uploading it under public webroot. The application stores leads before attempting SMTP; SMTP errors must not discard the saved lead.

**Public values only:** `public_html/assets/site-config.js` contains `googleBusinessProfileUrl`, `googleReviewRequestUrl` and two `features` switches. An API key, OAuth refresh token, admin password, database password or SMTP password must **never** go in that JavaScript file.

## 3 — Database setup (fresh vs existing staging)

1. Export and verify a complete protected backup of the existing database (if any), and record rollback procedure.
2. Create a dedicated restricted DB/user in Hostinger; grant only the permissions needed by the app.
3. **New installation:** import `secure-setup/schema.sql` into the intended empty database.
4. **Existing V7 database:** back up first, then manually apply **exactly once** `secure-setup/migrations/2026-11-23-v8.sql`; **do not** import the entire schema again as a migration or run it on an unverified database. It adds `partners.session_version` and `jobs.tracking_expires_at`.
5. Set private config `database.enabled=true` only after database connection and schema verification.
6. Start `leads.enabled=false`, `portal.enabled=false`, and public `leadSubmission=false`. Keep feature switches OFF during negative-path and privacy tests.

Schema: `leads` (consent-based inquiries), `partners` (hashed passwords + session version), `jobs` (owner quote, partner cost, assignment, SHA-256 tracking token + expiry), `job_activity` (owner/partner audit events). Never import synthetic tests into production.

## 4 — Security workflows requiring explicit staging verification

- **Lead API:** HTTPS JSON POST, same-origin browser guards, consent, length validation, private rate limiter, insert into MySQL, opaque `ATL-*` receipt. No public lead data endpoint. Check honeypot, repeated submit, oversized JSON, invalid consent, error handling, and optional SMTP. Production acceptance requires real DB and deliverability tests.
- **Owner portal:** `/portal/`; CSRF token, sessions with Secure/HttpOnly/SameSite cookie, password hash, throttling, idle expiry and role controls. Test wrong password, sign-in/out, idle expiry, forced password rotation and revoked partners. Owner changing admin password hash invalidates existing owner sessions.
- **Partner portal:** assigned cases only; no customer PII or profit numbers. Password reset/disable bumps `session_version` and revokes existing sessions at next authenticated request. Partner can document assessment but **cannot** start work from `assessment`/`quote_pending`/`awaiting_customer_approval` without owner-recorded approval. They cannot mark a cancelled or closed case in progress.
- **Quotation approval:** owner must set positive customer quote before awaiting approval; manual approval requires an external evidence method and reference. Record only a minimal evidence ID, not attached sensitive documents. This is a human-entered record, **not** cryptographic proof or customer e-signature. Changing approved pricing requires reopening quotation workflow.
- **Customer tracking:** 256-bit random bearer token, SHA-256 hash only in DB, 90-day default expiration (configurable 7–365), manual rotation revokes old link. The limited page displays case code, generic title, status and update date, not personal/contact info or amounts. Send link privately; HTTP logs/browser history may still contain token URLs. Use authenticated accounts for higher-risk use cases.
- **Track link origin:** `public_base_url` must be a trusted HTTPS origin and MUST be set to the correct staging host during staging, not built from untrusted request Host headers.
- **Privacy:** `/privacy/` is a **DRAFT**. Approve true contact details, data retention and partner sharing before allowing submissions. Do not collect customer device files, passwords, identity documents or confidential data in public forms.

## 5 — Staging acceptance checklist (must run before live feature flags)

```bash
# Repository-local validation, not production proof
for f in public_html/assets/*.js; do node --check "$f"; done
find public_html secure-setup -name '*.php' -exec php -l {} \;
php tests/workflow_rules.php
python tests/security_static.py
python tests/site_integrity.py
python tests/http_smoke.py
python tests/browser_smoke.py  # requires Chromium + Python Playwright
```

On actual **HTTPS staging**: confirm full responsive website, service routes, original logo, screenshots and privacy copy. Verify portal disabled returns 503 before activation; Google endpoint disabled returns 503. Verify MySQL import/migration and a **synthetic** customer lead; admin lead list; partner creation/login/password reset/disable; partner status blocked without approval; recorded owner approval, audit notes, quote/cost confidentiality; tracking token expiration/rotation. Verify unauthorized partner cannot enumerate other jobs. Exercise real SMTP accepted message and inbox delivery (or document that notifications are OFF while DB leads remain authoritative).

Once all tests pass and privacy is approved, enable server `database.enabled` and `leads.enabled`, then public `features.leadSubmission=true` **in staging**. Enable `portal.enabled` only after partner access tests. Keep `google.enabled=false` and `features.googleReviewsApi=false` until Google authorization is independently approved and successfully tested. Test that frontend never falsely claims an inquiry was received when the backend fails.

## 6 — Google Business Profile approval (independent of deployment)

Google Cloud project `pristine-abacus-515115-q2` / project number `465361008630`. The owner's Business Profile first-live notification was **4 October 2026**. API access request on 18 November 2026 stopped at the 60-day eligibility requirement. A cautious reapplication date is **5 December 2026** if the actual verification date was 4–5 October, but **eligibility alone does not grant approval**. Do not bypass access controls or invent OAuth tokens. Obtain authorized consent/OAuth refresh token privately, enable appropriate Google Business Profile APIs, verify correct account/location IDs, and only then enable server and browser flags. A direct public Google review invitation URL may be added after the owner supplies and verifies it; it requires no OAuth secret.

The Google review display is restricted to live authorized server API data; it never creates fake ratings or customer reviews. Actual Google OAuth redirect URIs must match a real secure backend route; this project does not yet implement an interactive OAuth setup/callback flow.

## 7 — Retention, rollback and release controls

`secure-setup/maintenance/prune.php` is CLI-only and must be copied privately to `secure/maintenance/` before use. **V8.5 safety:** running without arguments performs a **read-only eligibility count**; deletion requires the explicit `--run` flag. It only deletes unassigned leads older than a privately configured and approved integer `retention_days` (30–3650), excluding leads with pending/sending/dead email alerts and **all** leads linked to service cases. Review legal retention, backups, SMTP alerts, and case/audit deletion separately; do not automate deletion of active customer cases.

```bash
php /path/to/domain-root/secure/maintenance/prune.php --dry-run
# Only after reviewing the count, backups and retention policy:
php /path/to/domain-root/secure/maintenance/prune.php --run
```

**Important upgrade:** Any existing cron invoking `prune.php` without `--run` becomes **read-only** after this change. Do not update production cron jobs before staging acceptance and a deliberate retention policy decision.

Before production cutover: record original website backup, restoration test, database backup and rollback contact. Get explicit owner approval for any overwrite of `aaditechs.in`. Keep staging credentials separate from production. Never deploy directly from an unverified ZIP to the live domain. Change public flags only after its matching server-side integration is validated. Confirm that Google API sync, SMTP and host environment are truly working; their status cannot be inferred from a Git push.

## 8 — Pending external blockers

1. Hostinger account path/SSL/PDO MySQL/cURL and configured staging environment: **not verified**.
2. Data Privacy notice, partner terms and business contact/location details: **owner review pending**.
3. Real MySQL/SMTP end-to-end tests and security acceptance: **pending**.
4. Google Business Profile API quota/approval and OAuth authorization: **pending**.
5. Optional production-ready AI/LLM assistant, admin 2FA and a customer self-approval/signature tool: **not implemented** (future phase).
6. The separate ChatGPT Site private preview may still show V6; this repository source is authoritative for V8. No unattended production deployment is part of this release.

Read `docs/ARCHITECTURE.md`, `docs/PROJECT_STATUS.md`, `docs/SECURITY_CHECKLIST.md`, and `docs/GAP_AUDIT_V8.md` to hand off work to another coding agent without guessing.

## 9 — Hostinger staging preflight and CI (23 Nov 2026)

- New read-only CLI check: `secure-setup/maintenance/preflight.php` (copy privately to `secure/maintenance/preflight.php` on Hostinger). It checks PHP/extensions, `secure` placement, placeholder/flag states and optional `--check-db` read-only SQL/schema access **without printing credentials**. Run local/CI `php secure-setup/maintenance/preflight.php --source-only` only to validate source structure.
- Exact staging verification and release gates: [`docs/STAGING_ACCEPTANCE.md`](docs/STAGING_ACCEPTANCE.md). The document reports staging **not yet deployed**. Do not interpret offline results as live MySQL/SMTP acceptance.
- `.github/workflows/source-validation.yml` runs source checks on pushes/PRs but **never deploys**; the real Hostinger account connection and target-site confirmation remain prerequisites.


## 10 — V8.2 opt-in owner 2FA and tracking privacy (source hardening)

- Owner portal now supports optional **RFC 6238 TOTP** (6 digits, 30-second window ±1 step), via `portal.admin_totp_enabled` and `portal.admin_totp_secret` in **private** config. Default remains **false**, including after deploying this code. The sign-in screen accepts a 6-digit authenticator code; it is required for owner authentication only when TOTP is enabled. Partner accounts remain password-only and must not be considered MFA-protected.
- To enroll, on a trusted offline machine generate a cryptographically random **20-byte minimum (160-bit)** base32 secret, provision it manually to your authenticator app, and save the secret only in private `secure/aaditechs-config.php` (`admin_totp_secret`). Check the actual phone/server clocks and verify at least two consecutive codes on staging. Configure a safe offline break-glass procedure and backup of the private config before turning `admin_totp_enabled=true`. **Do not commit the secret, upload it into `public_html`, paste it in chat, or embed it in an emailed QR code.**
- If an authenticator is lost, only a trusted hosting administrator should regain access via the protected config and credential rotation; never build a public 2FA bypass. Enabling 2FA and testing recovery are still **owner-controlled staging tasks**.
- The customer bearer-token tracking page no longer shows the free-form case title (which could inadvertently contain customer-identifying information). It shows only case ID, status and last update date. The token URL itself remains sensitive: share privately, avoid analytics/log forwarding, rotate after suspected exposure.
- On owner/partner login the anonymous CSRF token is rotated. Changing the owner password hash, TOTP on/off setting or TOTP secret invalidates existing owner sessions at their next request. Existing logged-in sessions and role boundaries retain their prior protections.

## V8.3: disposable database/HTTP integration tests (no deployment)

The `source-validation.yml` workflow has a separate `disposable-mariadb-integration` job. It runs a MariaDB container with synthetic records, PHP 8.3 PDO MySQL and `tests/integration_mysql_http.py`. A disposable, self-signed localhost TLS proxy carries browser Secure cookies; a localhost-only PHP router simulates the backend `HTTPS` flag. This certificate is only for synthetic loopback testing. **This does not test a real SSL certificate, Hostinger infrastructure, email delivery, Google API approval or backup.** Never deploy `tests/` to public webroot. Source `secure/aaditechs-config.php` is test-generated, gitignored, and created only in an isolated CI workspace; real Hostinger private config must be provisioned separately.


## V8.4 — Durable owner alert delivery (code implemented, hosting NOT yet tested)

- **Fresh staging DB:** import the current `secure-setup/schema.sql` (includes `notification_outbox`).
- **Existing V8.x DB:** back up first and apply `secure-setup/migrations/2026-11-23-v84.sql` once. Do not import the full schema over live data.
- In private `secure/aaditechs-config.php`, leave `mail.enabled=false` until SMTP, privacy, database and cron acceptance pass. Public lead submissions only enqueue an alert when mail is enabled; alerts are **not** sent in the HTTP request. No historical backfill happens when toggling mail ON.
- Place `secure-setup/maintenance/notification-worker.php` in the private `secure/maintenance/` directory, **not** public_html. Install PHPMailer privately at `secure/vendor/` with Composer.
- Run **read-only** `php secure/maintenance/notification-worker.php --dry-run` then verify configuration and controlled test mailbox before activating scheduled `php secure/maintenance/notification-worker.php --run` with Hostinger Cron Jobs (every 5–15 minutes, only after staging approval).
- Each successful lead transaction stores its alert intention in MySQL. Cron claims up to 20 due alerts, retries temporary SMTP failures up to six attempts with backoff, and marks permanent failures `dead`. Owner-only portal shows aggregate delivery states and offers manual dead-alert requeue. Never claim mail delivery until test inbox confirms.
- Delivery is **at least once**, not exactly once: after a crash between SMTP acceptance and DB acknowledgement, duplicate email may occur. The persistent reference lets the owner deduplicate. Cron must be configured and monitored separately.
- No real SMTP credentials, user contact details, logs or queue exports belong in public GitHub.

V10.1: configure Meta separately in private config, default OFF. Follow docs/V101_META.md; manual identity check and explicit worker invocation, no live OAuth token issuance or schedule installed. Stop workers before target/credential rotation. Never requeue uncertain posts.

V10.2: private social.network flags and credentials, explicitly verify account access before test posting; GBP eligibility remains pending. See docs/V102_NETWORKS.md. No media transfer, automatic schedule or token refresh installed.

V10.2A: private 0700 media storage, 0600 MP4, manual authenticated SFTP, 20 MiB cap, verify actual host PHP memory/quotas and authorized OAuth before staging. No upload via public webroot. See docs/V102A_MEDIA_UPLOAD.md. Explicit owner approval and CLI only.

V10.2B: use authorized Facebook Login for Business linked professional Instagram test account, verified format and explicit public Reel approval. Direct binary transfer, no public media URL; check processing manually before publish. See docs/V102B_INSTAGRAM.md.

V10.2C: verify private PNG/MP4 and PHP cURL/CURLStringFile, memory and quota before actual approved Facebook Page media test. Local Pillow, FFmpeg and ffprobe required. Explicit public approval; no media cron. See docs/V102C_FACEBOOK_MEDIA.md.

V10.2D: independent LinkedIn/X PNG flags, verify permissions, version, quotas, storage and upload URL before approved test. See docs/V102D_NETWORK_MEDIA.md.

V10.2E: before enabling media fetch, validate HTTPS route, CDN/access log query redaction, no cache, quota, approved JPEG and token expiry. No customer data or permanent public assets. See docs/V102E_META_IMAGES.md.

V10.2E: leave fetch_log_redaction_verified=false until staging access/CDN logs actually redact bearer queries. Instagram photo derivative 1080x1350, Threads 1080x1080. Positive synthetic core delivery is not live HTTPS acceptance.

V10.2F: private inventory and retention flags OFF. Back up DB/files, stop workers/transfers, pause and verify no in-flight requests before explicit per-file quarantine/restore. No deletion. See docs/V102F_MEDIA_STORAGE.md.

V10.2G: enable oauth_lifecycle before any live social activation; set per-provider generation/deadline, stop workers/pause before private rotation, check identity read-only and revoke old credential through provider console. See docs/V102G_OAUTH_HEALTH.md.

V10.2H: apply media resource ledger migration before existing LinkedIn/X image workers run. Inspect /mediarecovery for uncertain rows; never requeue automatically. See docs/V102H_MEDIA_RECOVERY.md.

V10.2H.1: apply after V10.2H, LinkedIn video flag OFF. Verify actual API scope/version, private MP4, signed multipart ranges and intermediate URN/ETag finalization; Reel receipt before binary. See docs/V102H1_VIDEO_BATCH.md.

V10.2H.2: apply migration after V10.2H.1, including X ledger and default image asset_kind on existing Meta rows. New flags x_video_enabled and threads_video_enabled OFF. Verify X user scope/processing/expiry and Threads HTTPS full MP4 fetch, redacted logs, no cache and host memory/quotas before enabling. No HTTP Range support; actual provider compatibility unverified. See docs/V102H2_VIDEO_BATCH.md.

V10.2H.3: apply migration after V10.2H.2. Carousel and inbox import flags OFF by default. Verify separate private 0700 inbox, authorized manifest and hashes, carousel provider scope and ordered child receipts, bearer redaction and actual range responses. Remote SFTP delivery remains a separate manual step. See docs/V102H3_CAROUSEL_TRANSFER.md.

V10.2I/J: no DB migration required beyond V10.2H.3. Configure dedicated private SFTP user, independently verified host key, 0700 inbox outside webroot, 0600 config/key/known_hosts/assets and local OpenSSH. Explicit --send only; server-side import still required. Set oauth_lifecycle.alerts_enabled only after approval; existing owner daily reporting and scheduler require independent setup. No auto refresh or cron installation. See docs/V102IJ_TRANSFER_HEALTH.md.

V10.2K/V10.3: apply 2026-11-24-v102k.sql after V10.2H.3 (V10.2I/J has no migration). Telegram media and communications flags OFF. Verify separate business bot, exact channel and administrator post permission; approved private files and receipt. Import only authorized aggregate counts via private CLI. No mailbox, WhatsApp, SMS or telephony credentials or live connectors. See docs/V102K_V103_COMMUNICATIONS.md.

V10.3A/V10.4: apply secure-setup/migrations/2026-11-24-v103a-v104.sql after V10.2K/V10.3. New Gmail, local batch and followup flags OFF. Verify separate authorized business mailbox, metadata-only Google consent and app verification requirements, private credential expiry and generation, no response logging; validate source rights, batch rollback and owner reminder/report delivery. No real connectors or schedule enabled. See docs/V103A_V104_INTELLIGENCE.md.

V10.5: no new schema migration; all previous migrations through V10.3A/V10.4 required. Set intelligence_dashboard.enabled=true only in authorized isolated staging with portal and growth enabled. Confirm existing HTTPS/session, password and TOTP configuration, no cache/index, owner-only link and direct-route rejection for guests/partners. Review 7/30-day data semantics, responsive layout and module-off behavior. No live deployment or write controls. See docs/V105_OWNER_DASHBOARD.md.

V10.5A/V10.6A: no new SQL migration. Use secure-setup/maintenance/release-contract.json for full ordered migration inventory (28 files, not all are missing on every database). Copy release-check.php and release-contract.json together to private secure/maintenance; run php release-check.php --check-db only against approved staging after stopping workers and pausing growth. Required-column/pause verification only, not backup, index/type or provider proof. Local CI replays V9.6 on a disposable database and compares schema metadata. Do not run tests/migration_replay.py on hosting. CSV export and communications batch flags default OFF. Verify completed IST/UTC semantics, aggregate-only exports, source consent, conflicts and private download handling. See docs/V105A_V106A_RELEASE_READINESS.md.

V10.6B: no new migration. Keep communications.gmail.renewal.enabled and communications.retention_enabled OFF until authorized acceptance. Stop workers and pause growth before explicit renewal or retention; private candidate directory 0700 outside public_html, candidate 0600, no overwrite. Verify OAuth consent, exact scope and private rotation; never log or commit candidate. Retention dry-run first, verified backup/restore and approved policy before digest-confirmed apply. No automatic renewal, deletion, cron or deployment. See docs/V106B_CREDENTIAL_RETENTION.md.

V10.6C: no new migration. Keep communications.bridge_enabled and each source authorization OFF until approved. Each source needs an independently authorized aggregate endpoint, per-source private credentials and generation, TLS and signed contract. Run communications-bridge.php --check CHANNEL without networking, then explicit --fetch only after source consent. Release source check requires repository layout; copy release-check.php, release-gate-lib.php and release-contract.json together for private DB check. Stop workers and pause growth before read-only check; any listed unresolved state blocks, no automatic replay or clearing. See docs/V106C_COMMUNICATIONS_RELEASE.md.

V10.6D: apply 2026-11-24-v106d.sql after V10.3A/V10.4 (V10.5 through V10.6C no migrations). Updated contract: 29 ordered migrations, 41 tables, 31 source file hashes. Copy reconciliation-lib.php alongside release-gate-lib.php, release-check.php and release-contract.json. reconciliation.enabled OFF; stop workers and pause before private review. Require independent provider evidence; no original publication mutation or automatic replay. Verify backup artifact privately using workers/backup-verify.py, then independently restore in authorized isolated environment. Gmail candidate --inspect-candidate and --verify-candidate digest do not replace config. See docs/V106D_RECONCILIATION_BACKUP.md.

V10.6E: apply 2026-11-24-v106e.sql after V10.6D. Contract: 30 ordered migrations, 42 tables, 32 source file hashes. Native usage flags OFF; dedicated authorized WABA analytics credential and restricted Twilio usage-read key, local expiry and generation required. Private CLI --check then --capture only after acceptance. CI dump/restore runs only on disposable synthetic MariaDB; real backup/isolated restoration remains mandatory. See docs/V106E_NATIVE_USAGE_RESTORE.md.

V10.6F: apply 2026-11-24-v106f.sql after V10.6E. Contract 31 migrations, 43 tables, 33 hashes. Telegram album and WhatsApp inbound flags OFF. Verify exact manifest pair and owner approval, business bot/admin permissions, group receipt and visibility. Webhook requires dedicated authorized Meta app/WABA/phone, independently generated verification and dedupe secrets, HTTPS, request/log redaction and quotas; confirm retention procedure before activation. No automatic reply, webhook subscription or production configuration. See docs/V106F_ALBUMS_INBOUND.md.

V10.6G: apply 2026-11-24-v106g.sql after V10.6F. Contract 32 migrations, 44 tables, 34 source hashes. Twilio SMS and voice status callbacks and WhatsApp cleanup flags independently OFF. Verify exact configured HTTPS URL, dedicated authorized subaccount and auth token, callback semantics and log redaction. Never replace call-control webhook with voice status endpoint or existing SMS routing without approved integration review. Before cleanup, approve policy, backup/restore, stop workers and pause growth; dry-run first. See docs/V106G_INBOUND_RETENTION.md.

V10.6H: apply 2026-11-24-v106h.sql after V10.6G. Contract 33 migrations, 45 tables, 35 source hashes. Mixed carousel and communication review flags OFF. Verify legacy digest compatibility, exact approved video hash, asset order, actual provider codec/aspect/processing and bearer range delivery; validate review source and retention blocking before enabling. See docs/V106H_MIXED_REVIEWS.md.

V10.6I: apply 2026-11-24-v106i.sql after V10.6H. Contract: 34 migrations, 46 tables, 36 source hashes. Search Console flag and authorization OFF. Dedicated privately consented read-only OAuth app and refresh grant required; no sharing Gmail/GBP tokens. Private CLI --check then --capture only after approved property/scope acceptance. Review local credential deadline, provider response shape, finalized Pacific Time dates and missing-day coverage. No Search Console write, indexing request or deployment. See docs/V106I_SEARCH_CONSOLE.md.

V10.6J: apply 2026-11-25-v106j.sql after V10.6I. Contract 35 migrations, 48 tables, 37 source hashes. History and crawl import flags independently OFF. Verify actual Search Console property (source sitemap uses aaditechs.in), authorization and generation. Explicit local crawler --capture-authorized only with owner permission, reviewed robots policy and approved network environment; import sanitized report within 24 hours through private CLI. Stop/pause rules apply to database import; local crawl has no DB connection. Actual crawl, retention and isolated backup/restore remain pending. See docs/V106J_SEO_HISTORY_CRAWL.md.

V10.6K: no new SQL migration; contract remains 35 migrations, 48 tables, 37 hashes. Run workers/local-runtime-check.py only on trusted local machine, with independently pinned installed model and network egress blocked for genuine offline inference. Optional synthetic inference and codec checks do not authorize production. SEO history retention and GBP media read flags OFF. Approve retention and verify backup/restore before paused digest-confirmed cleanup; --check-config GBP is local, --check-read is an explicit scoped provider GET only. See docs/V106K_RUNTIME_RETENTION.md.

V10.6L: no new migration; contract remains 35 migrations, 48 tables, 37 hashes. Discovery cadence and recovery flags OFF. Explicitly authorize sources and review minimum interval (example 1440 minutes) before enabling. Existing --dry-run remains no-network configuration validation, --run is explicit. No cron installed. Stop all workers, pause, inspect interrupted run and confirm exact digest only after independent process verification. Do not infer imported counts from interrupted ledger. See docs/V106L_DISCOVERY_AUTOMATION.md.

V10.6M: apply 2026-11-25-v106m.sql after V10.6J (K/L no migrations). Contract 36 migrations, 49 tables, 38 source hashes. New flags OFF. Review a private --plan OBJECTIVE before --apply OBJECTIVE SHA256; no model call or publication from batch CLI. Stop workers and pause before Twilio retention; verified backup/restore and explicit policy approval required. Replay hashes remain indefinitely unless separate reviewed migration/policy; never rotate dedupe key casually. See docs/V106M_BULK_COVERAGE_RELEASE.md.

V10.6N: no migration/flag changes. Private creative batch optionally takes LIMIT (1–126) on both --plan and --apply; review selected cells and use the same limit and fresh v2 digest. Old v1 plans are invalid. Verify synthetic CI and actual private staging before use; no generation or publishing from batch creation. See docs/V106N_BOUNDED_CREATIVE_REVIEW.md.

V10.6O: no migration. Discovery scheduler flag OFF. Only after source authorization and staging acceptance enable cadence and scheduler; private discovery-feed.php --schedule-check then --schedule-status; approved scheduler may invoke --scheduled-run. No cron installed; stop workers and pause before recovery. See docs/V106O_DISCOVERY_SCHEDULER_INTEGRITY.md.

V10.6P: no migration or new flags. Existing private dashboard and CSV authorization apply. Creative coverage requires existing creative batch flag; web breakdown requires consented analytics. Confirm complete catalog, missing-vs-zero, private headers and aggregate CSV on isolated staging before enabling. See docs/V106P_OWNER_COVERAGE_INTELLIGENCE.md.

V10.6Q: no migration/new flag. Existing private creative transport gate remains. For multi-job invocation provide independently verified local model digest and --limit 1–20. Check actual offline runtime, protected token, TLS, stop behavior and non-overlap before scheduling. See docs/V106Q_PINNED_LOCAL_WORKER.md.

V10.6R: no migration or new flag. With workers stopped and growth paused, capture schema metadata from independently trusted isolated fresh release, record SHA256, compare against isolated restored target using --compare-schema. Use private reference and canonical gzip paths. See docs/V106R_SCHEMA_RESTORE_GATE.md; metadata parity is not data completeness or production acceptance.

V10.6S: no migration/new flags. Explicit local batch-plan then batch-render for 1–12 exported approved text jobs. Keep private, verify individual manifests and actual imagery, review current revision before server registration; no automatic transfer or publish. See docs/V106S_REVIEWED_MEDIA_BATCHES.md.

V10.6T: no migration/new flag. Verify every approved media revision and completed batch index independently, use local-only batch preflight, explicit --send only for authorized isolated SFTP. Stop and inspect partial remote files on uncertainty; individual approved server import required. See docs/V106T_REVIEWED_MEDIA_TRANSFER.md.

V10.6U: no migration. New inbox_batch_enabled OFF; authorize isolated staging first. Use --batch-plan then --batch-apply SHA256 for 1–12 individually approved scopes; preserve source and inspect partial destination files after failure. See docs/V106U_REVIEWED_SERVER_IMPORT.md.

V10.6V: no migration/new flag. Private --reference-audit is bounded and read-only; --quarantine-plan requires approved retention, paused growth and 1–50 explicit names. No bulk move or deletion. See docs/V106V_MEDIA_INTEGRITY_AUDIT.md.

V10.6W: no migration. New oauth_lifecycle.batch_health_enabled OFF. Explicit private --batch-plan and --batch-check reviewed digest for authorized providers only with workers stopped and growth paused. No renewal or revocation. See docs/V106W_OAUTH_HEALTH_BATCH.md.


## V10.6X — reviewed opportunity follow-up batches (source only)
Private OFF-by-default 1–50 reminder set/reschedule/complete/cancel batches, read-only exact-state plan, digest-bound atomic apply, per-item audit, single-action audit rollback and 73 scope + 84 service regression cases. No external outreach or production changes. See [V10.6X](docs/V106X_REVIEWED_FOLLOWUP_BATCH.md) for operator gates; CI and actual staging pending.


## V10.6Y — reviewed communications and retention integrity (source only)
New default-OFF private 1–50 aggregate review batch across email, WhatsApp, SMS and calls; exact-state plan/apply, per-item audit, local rollback and full-state retention digest. 108 scope and 24 channel scenarios authored, plus retention drift tests. Prior V10.6X CI passed. New CI and actual hosting/consent/retention acceptance pending. See [V10.6Y](docs/V106Y_COMMUNICATION_REVIEW_BATCH.md).


## V10.6Z — private page-level SEO diagnostics (source only)
Optional detailed seven-page observation v2 with strict path/status/finding consistency, aggregate-only persistence, v1 compatibility, atomic Search Console snapshot/history/audit and live SEO audit rollback. 56 crawler + 126 report validation scenarios authored. Prior V10.6Y CI passed; new CI and actual crawl/provider/staging acceptance pending. See [V10.6Z](docs/V106Z_PAGE_SEO_DIAGNOSTICS.md).


## V10.7A — competitor editorial evidence (source only)
Offline private operator-supplied own/peer comparison across 14 services and six editorial signals (84 cells), 1–5 peers, explicit unknown evidence, source references and review-only gap suggestions, 504 synthetic decision cases. No scraping, actual competitor claims, rankings, publishing or database changes. Prior V10.6Z CI passed; new CI and real evidence acceptance pending. See [V10.7A](docs/V107A_COMPETITOR_EVIDENCE.md).


## V10.7B — reviewed editorial task batches (source only)
Private default-OFF competitor-to-SEO review handoff, 1–84 selected cells, exact reviewed plan, stale digest rejection, atomic audited pending-review task creation and existing-task preservation. 84 export + 168 scope cases and synthetic 84-task database test authored. No live competitor data, external publishing or production changes. See [V10.7B](docs/V107B_COMPETITOR_REVIEW_TASKS.md).


## V10.7C — restore row-count reconciliation (source only)
Private read-only count capture/compare across 49 contract tables; exact metadata/backup binding, canonical reference, table-specific match/drift, pause and worker gates, backup FIFO/size safeguards. 98 table-specific scenarios and disposable DB count checks authored. Prior V10.7B CI passed; new CI and actual isolated restore pending. See [V10.7C](docs/V107C_RESTORE_COUNT_RECONCILIATION.md).


## V10.7D — keyed restore content fingerprints (source only)
Private bounded read-only content fingerprint comparison across 49 tables; keyed per-table HMAC with primary-key order, binary field framing, metadata/backup binding and canonical reference. 98 table cases and same-count mutation regression authored. Prior V10.7C CI passed; new CI and actual isolated restore pending. See [V10.7D](docs/V107D_RESTORE_CONTENT_FINGERPRINTS.md).


## V10.7E — private media restore parity (source only)
Full bounded active/quarantine asset verification and keyed parity across 15 reference sources, missing-reference readiness gate, no raw asset names exported, three staging flag visibility updates. 51 manifest matrix cases authored. Prior V10.7D CI passed; actual restoration and new CI pending. See [V10.7E](docs/V107E_MEDIA_RESTORE_PARITY.md).


## V10.7F — staging configuration and schema preflight (source only)
113 explicit flag states, 76 child flag dependency checks, strict all-off isolated staging baseline, all 49 contract tables/required columns and 437 matrix cases authored. Prior V10.7E CI passed; new CI and actual hosted acceptance pending. See [V10.7F](docs/V107F_STAGING_PREFLIGHT.md).


## V10.7G — exact deployment integrity (source only)
Commit-derived bounded SHA-256 inventory of all tracked public files/private maintenance scripts, private staged-copy parity, missing/drift/extra/unsafe checks, preflight config path and permission gate, 102 synthetic file cases. Prior V10.7F CI passed; new CI and actual hosting pending. See [V10.7G](docs/V107G_DEPLOYMENT_INTEGRITY.md).


## V10.7H — strict JSON boundaries (source only)
Seven reviewed decode calls reject repeated decoded members; existing scope/authorization controls retained. 200 matrix cases and six integrated scope tests authored; development push CI trigger added. V10.7G PR/CI unconfirmed at initial check; new CI and actual hosting pending. See [V10.7H](docs/V107H_STRICT_JSON_BOUNDARIES.md).


## V10.7I — local native video review candidates (source only)
Offline fixed CogVideoX candidate adapter, reviewed 1–12 job/model/font/seed plan, 14-service/9-channel coverage, bounded private motion rendering and review-only index, no transfer or publication. 126 plan cases and synthetic moving-frame codec tests authored. Prior V10.7H CI passed; actual GPU/model, new CI and hosted acceptance pending. See [V10.7I](docs/V107I_NATIVE_VIDEO_CANDIDATES.md).

V10.7I follow-up adds exact read-only candidate re-verification, complete index/bundle and per-job binding, asset integrity and explicit editorial disposition report with publication_authorized=false. No server import or actual review. Final-head CI pending.


## V10.7J — reviewed OAuth rotation and native-video CI correction (source only)
Seven provider replacement credentials can be privately planned and identity-verified without activation. 84 provider cases authored; separate default-OFF flag, HMAC binding, pause/config gates and fail-stop. Corrects V10.7I font fixture without relaxing production checks. New CI pending; actual provider renewal, GPU inference and hosted acceptance pending. See [V10.7J](docs/V107J_OAUTH_ROTATION_REVIEW.md).
