# Aaditechs — Complete Deployment & Secrets Runbook

Updated: 18 November 2026 (Asia/Kolkata)  
Website: https://aaditechs.in/  
Private redesign: https://aaditechs-modern-concept-2026.tiledparrot.chatgpt.site  
Google Cloud project: `Aaditechs Google Reviews` (`pristine-abacus-515115-q2`; project number `465361008630`)

> **READ THIS FIRST:** The Aaditechs public Hostinger website was **not replaced** by this work. The current private preview is a static Site, not a production PHP host. Google Business Profile API approval is **blocked until the profile has been verified and active for 60+ days**; the Google email from 4 October 2026 confirms the new profile was live that day. Earliest likely eligibility is 3–4 December 2026; retry from **5 December 2026**, subject to Google's decision. The OAuth client exists, but a redirect URI, production authorization, and refresh token are **not** complete.

## 1. Actual status — do not assume more is working

| Item | Status |
| --- | --- |
| HTML5 redesign and original Aaditechs orange/blue logo | Built in private preview |
| Data Recovery service, contact details, guided chat | UI ready; guided chat is **not a human/live AI operator** |
| Contact form | Preview only; no customer inquiry transmitted |
| Google Business Profile | User confirmed verified; profile first went live 4 October 2026 |
| Google Cloud project / OAuth client | Created by owner; no credentials stored in source |
| `business.manage` OAuth scope | Configured; broad permission—limit live use to read-only operations |
| GBP API access | **Not approved** (60-day requirement) |
| Google review auto-sync | **OFF** until approved/configured/tested |
| Hostinger SMTP inquiry delivery | **OFF** until credentials, spam controls, privacy review and tests |
| Public Hostinger deployment | **NOT DONE**; requires backup and explicit overwrite approval |
| Review request direct link | **MISSING**; owner must copy it from GBP → Read Reviews → Get more reviews |

## 2. Deployment architecture

```text
Visitor browser (HTTPS)
   ├── static HTML/CSS/JS at https://aaditechs.in/
   ├── Google profile / review link (public; no secrets)
   ├── optional GET /api/google-reviews.php (server-side OAuth)
   └── optional POST /api/lead.php (server-side SMTP)

Hostinger shared hosting
   domains/aaditechs.in/
      public_html/
         index.html, assets/, api/
      secure/                          <-- OUTSIDE public_html
         aaditechs-config.php         <-- CLIENT SECRET, REFRESH TOKEN, SMTP PASSWORD
         vendor/                      <-- private PHPMailer dependency
         cache/                       <-- short-lived Google review response cache
         rate/                        <-- salted lead throttling counters
```

Private Sites preview **cannot execute PHP**. Its feature switches must remain OFF. Production can use PHP on Hostinger without WordPress, a Node.js server, or additional hosting plans.

## 3. What goes where? Secret and API key inventory

| Item | Where to obtain | Where to store | Browser/source? |
| --- | --- | --- | --- |
| Google OAuth Client ID | Cloud Console → Google Auth Platform → Clients | Protected `secure/aaditechs-config.php` (`google.client_id`) | Client ID alone is not secret, but keep configuration server-side |
| Google OAuth Client Secret | Same OAuth client, or saved credentials JSON | `secure/aaditechs-config.php` (`google.client_secret`) | **NEVER** |
| Google OAuth Refresh Token | **After approval**, owner authorizes `business.manage` using Google OAuth Playground or implemented HTTPS callback | `secure/aaditechs-config.php` (`google.refresh_token`) | **NEVER** |
| GBP account ID and location ID | Approved Business Profile APIs | `google.account_id`, `google.location_id` in secure config | IDs may be public, but no reason to expose |
| Google Maps/Places API key | Not required for this implementation | **Do not create** just for Business Profile API | N/A |
| SMTP host / username / password | Actual email provider's SMTP settings | `secure/aaditechs-config.php` (`mail.*`) | Password **NEVER** |
| Email recipient | Actual Aaditechs inbox | `mail.inquiry_recipient` | Address may appear publicly |
| Anti-spam rate salt | Generate a unique random 32+ character string securely | `rate_limit_salt` in secure config | **NEVER** |
| Google Business listing link | Owner-supplied `https://share.google/Zn5RDeewInDijwD3i`; **verify destination** | `dist/assets/site-config.js` | Yes; link only |
| Direct Google review request link | GBP → Read Reviews → Get more reviews | `dist/assets/site-config.js` as `googleReviewRequestUrl` | Yes; link only |
| GA4 Measurement ID / Search Console verification | Google Analytics / Search Console | Public HTML only when configured | Yes; no private credentials |

**Never upload the Google OAuth JSON to GitHub, `public_html`, chat, or `dist/assets`.** Never write a secret in `.env.example`, a JavaScript bundle, a build log or screenshot.

### Create the protected config (Hostinger hPanel)

1. In hPanel open **Websites → Manage → Files → File Manager** for `aaditechs.in`.
2. Navigate **one level above** the site's `public_html` and create `secure` there. If hPanel forbids it, **stop** and arrange a verified server-side protected configuration path; do **not** place secrets in `public_html` as a shortcut.
3. Copy the template `hostinger-backend/secure/aaditechs-config.php.example` to `secure/aaditechs-config.php`.
4. Replace all `REPLACE_...` placeholders with private values **on the server** only.
5. Ensure filesystem permissions limit access to the website account (`chmod 700 secure`, `chmod 600 secure/aaditechs-config.php` where SSH/permissions allow).
6. Leave both `google.enabled` and `mail.enabled` as `false` until each integration is independently tested.
7. Confirm `secure` is **not** reachable through `https://aaditechs.in/secure/...` (404/403, not file content).

**Important:** For a normal Hostinger domain layout, the private configuration path is `/home/<account>/domains/aaditechs.in/secure/aaditechs-config.php`. Actual absolute paths depend on your hPanel account. The PHP adapter intentionally expects `secure` as a sibling of `public_html`; validate this before deployment.

## 4. Source files and feature switches

| File | Role |
| --- | --- |
| `dist/index.html`, `dist/assets/*` | Responsive UI and original logo; suitable for static Sites preview |
| `dist/assets/site-config.js` | **PUBLIC** URLs and two integration on/off switches, no secrets |
| `dist/assets/integrations.js` | Real Google review display only when response is valid; lead form POST only when enabled |
| `hostinger-backend/public_html/api/bootstrap.php` | PHP config loading and safe JSON HTTP utilities |
| `hostinger-backend/public_html/api/google-reviews.php` | Uses Google OAuth refresh token **server-side**, fetches actual GBP reviews, stores at most a 15-minute limited private cache |
| `hostinger-backend/public_html/api/lead.php` | Validates inquiry, checks honeypot/rate limit and delivers via authenticated SMTP |
| `hostinger-backend/secure/composer.json` | PHPMailer server-side dependency |
| `hostinger-backend/secure/aaditechs-config.php.example` | **Template only**; never fill it with live secrets in Git |

For the private preview keep:

```js
features: { googleReviewsApi: false, leadSubmission: false }
```

**Only after production API/backend tests** change the corresponding feature to `true` inside `public_html/assets/site-config.js` and retest. The public config must not include any secret. When API access is pending, do not display invented review ratings, counts or customer names.

## 5. Google Business Profile API activation — after 60 days

1. On or after **5 December 2026** (assuming verification remained active), return to [Google's official prerequisites](https://developers.google.com/my-business/content/prereqs#request-access).
2. Use the Google account that **owns/manages** the verified Aaditechs profile.
3. Choose **Application For Basic API Access**, project number `465361008630`, official website `https://aaditechs.in/` and truthful internal business-use description.
4. Wait for Google to approve; do not interpret an existing Cloud project or OAuth client as proof of approval.
5. After approval enable **Google My Business API**, **My Business Account Management API**, **My Business Business Information API**, and **Business Profile Performance API** as needed in the same Cloud project.
6. Check Google Auth Platform → **Audience**. The wizard indicated **Internal** Google Workspace access. If the GBP owner's account is an external Gmail/different organization, configure an appropriate **External** audience and approved test users/production consent per Google's current requirements. Don't expose tokens or bypass organization policy.
7. Establish an HTTPS OAuth callback on the live site with an exact matching registered redirect URI, or use Google's documented OAuth Playground test approach. For the latter, register `https://developers.google.com/oauthplayground` as an **additional** Authorized redirect URI for the test client, choose **Use your own OAuth credentials**, authorize scope `https://www.googleapis.com/auth/business.manage` as the GBP owner, and save the resulting offline refresh token **only in server private config**. Remove unneeded test redirect URIs afterwards. OAuth refresh tokens issued while an External app is in Testing may expire after 7 days; check publishing/verification needs before relying on unattended sync.
8. Obtain the GBP account ID using the approved Account Management API, then the location ID using the Business Information API; use numeric account/location IDs, not your Cloud project number or Maps Place ID.
9. Fill secure `google.*` values and set `google.enabled = true` **only after API approval and one verified successful owner-authorized request**.
10. Test `/api/google-reviews.php` over HTTPS. It should return actual Google data or an explicit safe error; no secrets in responses. Verify Google attribution and content-display policy compliance. **Never generate sample reviews and display them as real.**
11. Finally set `googleReviewsApi: true` in the **production-only** `site-config.js`. Review cache is 15 minutes inside `secure/cache`, never in the public web folder. Read Google Business Profile [API content/storage policy](https://developers.google.com/my-business/content/policies) before enabling the widget.

OAuth scope: `https://www.googleapis.com/auth/business.manage`. Google Business Profile API does **not** use a browser-side Google Maps API key for this route.

## 6. Hostinger SMTP inquiry delivery — optional, independent of Google approval

1. Confirm which real mailbox receives inquiries (`info@aaditechs.in` is presently displayed on the site). Verify ownership and delivery first.
2. Obtain **actual SMTP** settings from the mailbox provider: SMTP server, TLS port, authorized sender mailbox, username and password/app-password. Do not assume the website's web host is its email host.
3. Inside the protected `secure` directory, install PHPMailer with Composer:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   The result must include `secure/vendor/autoload.php`. Install with PHP 8.2+ and `curl`/`mbstring`; enable TLS certificates.
4. Fill `mail.host`, `mail.port`, `mail.username`, `mail.password`, `mail.from_email` and `mail.inquiry_recipient` in the private config. Generate a unique `rate_limit_salt` with a password manager or secure random generator.
5. **Before activation**, publish an accurate privacy/contact-data notice covering collection, purpose, delivery, retention and customer contact, and verify business consent requirements. Do not accept confidential files or passwords in this public form.
6. Set private `mail.enabled = true`. Submit a test with a controlled mailbox; check inbox, spam and reply-to. Test invalid email, honeypot, repeated submissions, HTTPS, and error handling.
7. Set production `leadSubmission: true`. The public form now posts to `/api/lead.php`; on 503 it must show a real failure, not a false success message.

The integrated chat in this redesign is a **local guided assistant**, not staffed live chat; it does not send inquiries. A separate real chat provider or server is needed to deliver chat messages to Aaditechs staff.

## 7. Hostinger deployment checklist (public site remains untouched until approval)

### Before touching `aaditechs.in`

- [ ] Verify the exact website in hPanel matches `aaditechs.in` and identify its Hosting plan and document root.
- [ ] Export/backup existing `public_html`, databases (if present), and relevant `.htaccess`/rewrite rules.
- [ ] Note current DNS/SSL/mail setup; no nameserver, MX, SPF, DKIM or DMARC changes are needed for this HTML/PHP deployment.
- [ ] Preserve old site in a recoverable timestamped archive; test archive contents before any overwrite.
- [ ] Confirm original logo and contact details with business owner.
- [ ] Test on a **staging subdomain** or isolated staging directory first; do not send test forms to customers.
- [ ] Get owner's explicit approval to overwrite the current public site. **No automatic production deploy in this task.**

### Upload files to staging/production (after approval)

1. The web root contents of `dist/` must go into `public_html/` **directly**: `index.html` at root; `assets/` as sibling.
2. Copy `hostinger-backend/public_html/api/` into `public_html/api/`; PHP files remain executable only on Hostinger, **not** in static Sites preview.
3. Copy `hostinger-backend/secure/composer.json` and the private config template into a **secure sibling folder** of `public_html`, not publicly served.
4. Install Composer dependencies in `secure`, populate private configuration and keep both services OFF initially.
5. Ensure HTTPS is forced and all files have safe permissions. Review existing rewrite rules before editing `.htaccess`.
6. Verify no file named `.env`, `client_secret.json`, `credentials.json`, `aaditechs-config.php`, `composer auth.json`, or backup archive exists in `public_html`.
7. Test all navigation, image paths, logo aspect ratio, contact links and responsiveness. Confirm the old `Zenith Soles` and `Robotics Lab` group-company entries remain absent.
8. Test `GET https://<staging>/api/google-reviews.php`: **503 awaiting approval** is expected while feature disabled.
9. Test `POST https://<staging>/api/lead.php`: **503** is expected with SMTP disabled; **200** only after an actual message is delivered.
10. After independent verification, enable only features whose backend actually works.
11. Run backup/restore rehearsal and record successful HTTP/error-log checks; only then schedule public cutover.

### Useful checks

```bash
# Public files should load (200). Note: staging URL should be tested first.
curl -I https://aaditechs.in/
curl -I https://aaditechs.in/assets/aaditechs-original-logo.png
curl -I https://aaditechs.in/assets/integrations.js

# API should not claim success while disabled.
curl -i https://aaditechs.in/api/google-reviews.php

# Prevent leakage: must NOT return 200 with config contents.
curl -I https://aaditechs.in/secure/aaditechs-config.php
```

Do not paste authorization headers, OAuth secrets, email passwords, or token responses into shell history, screenshots, issue comments, Git or public logs.

## 8. Rollback

1. Switch both public feature flags to `false` first; the site gracefully falls back to Google profile links and a demo-only contact form.
2. If the deployment broke public pages, restore the timestamped `public_html` backup to the exact original document root and restore original rewrite rules.
3. Do **not** delete Google Cloud project, revoke OAuth credentials, change MX/DNS or destroy private configuration just to roll back frontend files.
4. Clear Hostinger cache if it serves the wrong version; confirm home page, original images, menu, call/mail links and expected source.
5. If secrets were accidentally served publicly, treat them as compromised: rotate/revoke tokens and passwords, remove the file, verify logs, and retest.

## 9. Troubleshooting table

| Symptom | Likely reason / first check |
| --- | --- |
| Google application ends with “doesn't meet criteria” | GBP profile under 60 days; wait until after 5 December, no false eligibility declaration |
| GBP API quota is 0 or Google My Business API invisible | API project approval not granted; enabling Performance API alone is not review approval |
| `redirect_uri_mismatch` | Exact HTTPS callback URL not listed in Google Auth Platform OAuth client |
| OAuth owner cannot authorize the application | OAuth Audience set to Internal but GBP owner uses Gmail/another organization; review audience and test-user setup |
| `invalid_grant` on token refresh | Revoked/expired refresh token, wrong OAuth client, or external Testing-mode expiry |
| Google reviews API returns 503 | Feature remains off, account/location IDs wrong, access missing, or server cannot reach Google |
| No real reviews display | Business may have none yet, API access pending, or frontend flag is off |
| Contact inquiry returns 503 | SMTP is disabled, credentials/vendor missing, sender rejected, or server error; check backend logs without leaking secrets |
| Missing pictures | Incorrect relative `/assets` upload paths or missing files; don't replace broken visuals with unrelated reused images |
| Form says “Preview complete” | Production `leadSubmission` switch is OFF by design; no email was sent |

## 10. Business growth milestones

- **Now:** Fix real company details; verify listing link; obtain GBP “Get more reviews” link/QR; use real photos; improve service descriptions and website pages for IT Support/Data Recovery in Mumbai; set up Google Search Console and GA4 with actual domain ownership; measure contact clicks and real inquiries.
- **After SMTP staging tests:** Enable working contact form and real lead delivery.
- **After 60-day eligibility and Google approval:** Test OAuth, discover account/location IDs, enable live verified reviews and internal Business Profile performance dashboard.
- **Later:** Build internal lead/analytics dashboard and review-reply workflow **with manual approval**. Do not auto-post replies or impersonate customers; no guaranteed rankings or fake ratings.

## 11. Final handoff data for another developer/AI agent

Canonical business domain: `aaditechs.in`; private redesign Site ID `appgprj_6afaca6db15081918cf21e927b08040d`. Main public logo asset: `dist/assets/aaditechs-original-logo.png`, **must not be replaced**. Current stage is **pre-production**. Current public JS flags are OFF. Google Business Profile first-live email: 4 October 2026. Google Project number: `465361008630`. OAuth client created, OAuth scope configured, but API approval denied by eligibility gate as of 18 Nov 2026. There is **no** authenticated external Google API session and **no** SMTP config accessible to this project. Do not assert that contacts, chats or reviews are connected. Keep `Zenith Soles` and `Robotics Lab` out of group companies. Do not fabricate customer testimonials, location photos, business statistics, or certification claims.

Pending inputs from owner: verified direct Google review link, exact profile URL destination, correct phone/address confirmation, actual Hostinger target/backup and authorized production cutover, SMTP provider settings (configured privately), and API approval + OAuth server credentials (configured privately).

---

# V7 ADDENDUM — AV, Lead Engine & Partner Operations (23 November 2026)

**This addendum supersedes any V6-only statements that live inquiries require SMTP alone.** V7 persists leads in MySQL first, with SMTP as optional notification. Nothing has been deployed to production as part of this code update.

## A. New public routes and assets

- `/` homepage now includes **Audio Visual & Collaboration** with conference room, auditorium and PA offerings.
- `/video-conferencing-solutions/`, `/auditorium-av-solutions/`, `/professional-sound-systems/` each have service-specific copy, FAQ and AV planner link.
- `/av-project-planner/` builds a scope with customer contact consent; does not quote prices.
- The enhanced Smart Guide is loaded from `assets/chat-v2.js`/`.css` on homepage and AV routes; it is deterministic, **not** an Ollama/AI API or live agent.
- `/privacy/` contains a **DRAFT**, not yet an approved legal notice. Verify and finalize before enabling leads.
- `/portal/` and `/track/` are backend endpoints; they must return unavailable until private configuration is complete.

## B. Hostinger safe directory layout

```text
<domain-root>/
  public_html/           <-- upload ONLY repository public_html contents here after staging backup
    index.html, assets/, api/, portal/, track/,
    av-project-planner/, video-conferencing-solutions/,
    auditorium-av-solutions/, professional-sound-systems/,
    privacy/, .htaccess, sitemap.xml, robots.txt
  secure/                <-- never reachable over web
    aaditechs-config.php <-- copy template and configure privately; never in Git
    vendor/              <-- Composer install from composer.json
    rate/                <-- private writable 0700; created automatically
    login-attempts/      <-- private writable 0700; created automatically
    maintenance/prune.php (optional CLI job)
```

The PHP endpoint paths assume `public_html` and `secure` are sibling folders. Confirm the real Hostinger document root before uploading. Do not replace the existing website until full staging tests and owner approval.

## C. Database provisioning

1. Create a new MySQL/MariaDB database and restricted user using Hostinger hPanel. Do not reuse unrelated production DBs.
2. Confirm PHP 8.1+, `PDO_MYSQL`, TLS and privileges. Import `secure-setup/schema.sql` with hPanel phpMyAdmin / approved database import tool. Back up any existing DB before migrations.
3. Copy `secure-setup/aaditechs-config.php.example` to `<domain-root>/secure/aaditechs-config.php` using Hostinger private File Manager / SFTP; enter `database.host`, `port`, `name`, `username`, `password` there, **never in Git/chat/public JS**. Set `database.enabled=true` after verifying connection.
4. Choose a strong private random `rate_limit_salt` of at least 32 characters and restrict private `secure/rate`/`secure/login-attempts` directory permissions.
5. Keep `leads.enabled=false` until privacy review and lead endpoint staging tests. In `public_html/assets/site-config.js`, `features.leadSubmission` remains `false` until the server-side feature works; then change and deploy on staging only first.

## D. Owner and partner authentication

1. Choose a unique administrator email not shared publicly. Generate a strong password and hash *on a trusted server* (do not paste raw passwords in ChatGPT). For example:

   ```bash
   php -r 'echo password_hash(readline("Admin password: "), PASSWORD_DEFAULT), PHP_EOL;'
   ```

   Enter `portal.admin_email` and `portal.admin_password_hash` into the **private** config, set `portal.enabled=true` only after HTTPS and DB checks.
2. Sign in at `https://staging.example/portal/`; test invalid password, per-IP throttling, CSRF, successful login, logout, admin/partner role isolation and idle session expiry. URL is an **example**, not an asserted staging domain.
3. Add each partner manually in admin dashboard with verified contact and 12+ character password. Share credentials via a trusted private channel. Disable and reset partner access from admin dashboard when needed.
4. Partner sees only assigned work, not customer contact fields or prices. Before assigning sensitive business scopes, make sure summary excludes personal or confidential customer data. Partner status updates do not auto-approve customer quotes.
5. Admin can create a case from a lead and assign a partner. It shows a tracking URL **once**; copy privately to the correct customer. URL is a bearer capability and only displays high-level status. Lost/exposed URL should be rotated by admin.
6. Owner must obtain actual customer quotation approval outside this portal before marking the case `approved`. There is no integrated signature, invoicing, payment gateway or automatic acceptance.

## E. Email notification

Install dependencies from `secure-setup/composer.json` using `composer install --no-dev --optimize-autoloader` **inside `<domain-root>/secure`**, or upload the Composer `vendor/` folder generated on a trusted machine if Hostinger shell lacks Composer. This is an example command; verify actual Hostinger shell permissions first. Set `mail.enabled=true` and private SMTP settings only after real sending tests. SMTP failures must not suppress stored lead references; inspect dashboard and server mail logs.

## F. Privacy, retention and partner coordination

Review `/privacy/` draft and replace with approved actual notice. Confirm legitimate purposes, exact retention, access, deletion and partner handling of customer data. Assign a retention policy, then set `retention_days` in the private config. Optional `secure/maintenance/prune.php` is **CLI-only**, checks `retention_days` and deletes only unassigned old leads. Do not schedule until approved and tested on staging. For projects/cases, manually close and review retention before deleting associated leads.

## G. Staging acceptance test checklist

- [ ] Verify original logo and all service pages load on mobile and desktop; service filter and chat flow work.
- [ ] Test AV planner in default mode: it must say **no submission or storage**.
- [ ] Verify contact form and chat still use demo mode while feature flag OFF.
- [ ] Test invalid content, spam/honeypot, consent handling, rate limit and JSON response when enabling staging leads.
- [ ] Submit one synthetic test lead; verify `leads` row, reference ID and optional SMTP notification.
- [ ] Admin login, partner creation, job assignment and status changes persist; password reset/disable removes access.
- [ ] Partner sees only assigned job without PII, owner quotes or partner costs.
- [ ] Customer tracker displays only code/title/status; rotate token invalidates old link.
- [ ] Verify direct HTTP access to `secure/`, `.env`, SQL backups is blocked. Inspect error logs for secrets/PII leakage.
- [ ] Test backup restoration and rollback before replacing `aaditechs.in`.

## H. Rollback / external dependency boundaries

Keep old `public_html` backup and DB snapshot in a protected location, not under webroot or Git. For a failed static deployment, restore previous web files and reset `assets/site-config.js` feature flags OFF. For a failed DB migration, restore from verified backup rather than dropping live tables blindly. Confirm Google API access separately; `google.enabled` and `googleReviewsApi` stay false until authorization is approved and tested. No changes here grant Google API permission.

**For code audit:** see `docs/PROJECT_STATUS.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_CHECKLIST.md`, and `tests/browser_smoke.py`.
