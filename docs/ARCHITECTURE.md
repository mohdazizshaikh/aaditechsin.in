# Aaditechs Lead Engine & Partner Operations — architecture

> **Version boundary:** This file describes the **existing V8 source architecture**, not the proposed V9 implementation. The new branch's modular PHP/MySQL growth-engine architecture is specified in [V9 architecture](V9/02_ARCHITECTURE.md) and [V9 module specs](V9/03_MODULE_SPECS.md). No V9 endpoint is live yet.

## Trust boundaries

1. Public static HTML: homepage, AV pages, guided chat, AV planner; `site-config.js` contains *only public* flags and nonsecret link values.
2. Public lead API `/api/lead.php`: TLS JSON POST, origin and consent validation, strict length validation, filesystem per-client cooldown (hash of IP + private salt), database INSERT. No customer record returned; only opaque short reference. Optional SMTP notification after DB success.
3. Private owner/partner portal `/portal/`: session login, CSRF token per session, password hashes, salted login throttling, owner/partner authorization, no-index/no-store/CSP, admin password-change session invalidation, partner password/disable session revocation. All SQL prepared. Partner role can only see its assigned jobs and never reads `leads` table directly in UI.
4. Customer tracking `/track/?token=<64 hex>`: token is 32 random bytes; database stores SHA-256 and configurable expiration (default 90 days). Public page reveals case code, title, owner-managed status, update date, no personal data or rates. Link possession grants limited access; rotate link on exposure.
5. Private server root sibling `<domain-root>/secure/`: `aaditechs-config.php`, PHPMailer Composer vendor, filesystem rate-lock directories, and CLI-only retention job. Not reachable via web server.

## Database

- `leads`: consent-based customer inquiry details. Do not accept passwords, confidential files or sensitive device data.
- `partners`: active flag, display name, specialty, email login, password hash and session version for revocation. Password is never stored plaintext.
- `jobs`: lead relationship, assigned partner, owner quotation, partner cost, status, SHA-256 tracking token hash and UTC expiry. No automatic financial calculations or promises.
- `job_activity`: role-scoped owner/partner update and audit notes (never public).

## Workflow

Lead intake → admin qualifies → creates case and optionally assigns partner → partner records assessment notes → admin reviews positive quote/cost and obtains documented customer approval outside platform → admin records method/reference and marks approved → partner performs → owner closes → private tracking page reflects status. Partners cannot initiate in-progress work until the approved state. The portal does not infer approval from a form, verify evidence independently, or send actual payment/contract requests.

## State flags

- `assets/site-config.js` `leadSubmission=false` means homepage/chat/AV builder never POST; they clearly show demo mode.
- `secure/aaditechs-config.php` `leads.enabled=false` means backend rejects writes even if public flag is accidentally enabled.
- `portal.enabled=false` blocks private portal and tracking, independent of public flags.
- `google.enabled=false` and `googleReviewsApi=false` retain the genuine-review integration but do not call Google API or display fictitious ratings.

## Security caveats & tradeoffs

- This is a **single-owner lightweight portal**, not a fully audited enterprise IAM or billing system. HTTPS, firewall/CDN rules, two-factor support and security review recommended before handling substantial customer data.
- **Server-side workflow rules** are pure functions in `api/workflow.php`; admin notes and approval evidence are only owner-visible.
- Tracking token links use a trusted configured HTTPS origin, not unvalidated Host headers, and expire by default after 90 days.
- Tracking tokens in URL can appear in web-server logs and browser history; set no-referrer/noindex, instruct customers to keep private, rotate when exposed; use authenticated customer accounts if stronger privacy is required.
- Per-IP file rate limiting is basic abuse prevention, not a WAF or CAPTCHA substitute. Add provider-level abuse control for public launch.
- `privacy/` is **draft**, must be adapted by owner/legal counsel before enabling live leads.
- Lead notifications are best effort; database and owner queue are authoritative. Avoid automated unsolicited marketing.
- Google OAuth approval independent of this app and may be denied; do not bypass it.

## Dependencies

- PHP >= 8.1, PDO MySQL, MySQL/MariaDB, TLS; `mbstring` optional fallback for validation, `openssl` for secure sessions/randomness.
- Composer + PHPMailer for optional SMTP; cURL required for existing Google Reviews endpoint.
- JS browser support: modern evergreen browsers, no external JS framework.
