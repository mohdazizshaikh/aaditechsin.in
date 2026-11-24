# Aaditechs — Hostinger deployment, secrets and security runbook

**Updated:** 23 November 2026 · **GitHub source:** `mohdazizshaikh/aaditechsin.in` → `main` · **Domain:** `aaditechs.in`.

> **Current status:** V8.5 source is implemented; latest verified GitHub CI includes disposable MariaDB tests and offline checks. **No production overwrite, Hostinger staging acceptance, live MySQL or SMTP verification, Google API approval, legal review or customer production traffic are claimed.** The pre-existing private ChatGPT Site preview may still be on an older version. All live lead/portal/Google flags start **OFF**.

## V9–V10 development addendum (24 November 2026)

Main V9.6 verified; V9.7–V10.2E stacked development branches are not deployed. Use [PROJECT_STATUS](docs/PROJECT_STATUS.md) for latest evidence. Review all missing additive migrations in release order (V8, V8.4, V9.1–V9.9, V10.0 then V10.1 then V10.2 then V10.2A then V10.2B then V10.2C then V10.2D then V10.2E; do not use lexical filename order); never blindly reimport a live schema. Fresh isolated database uses full schema.sql. Keep all growth/analytics/discovery/creative/creative_transport/media/expert/seo/social/owner-report flags OFF until individual acceptance. Private CLI scripts go in secure/maintenance, local Python workers on the trusted PC, no models or generated assets in public_html. Follow module runbooks and staging gates. SEO is source-only and has no authorized Search Console connection yet.

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
