# Aaditechs Lead Engine & Partner Operations — architecture

## Trust boundaries

1. Public static HTML: homepage, AV pages, guided chat, AV planner; `site-config.js` contains *only public* flags and nonsecret link values.
2. Public lead API `/api/lead.php`: TLS JSON POST, origin and consent validation, strict length validation, filesystem per-client cooldown (hash of IP + private salt), database INSERT. No customer record returned; only opaque short reference. Optional SMTP notification after DB success.
3. Private owner/partner portal `/portal/`: session login, CSRF token per session, password hashes, salted login throttling, owner/partner authorization, no-index/no-store/CSP. All SQL prepared. Partner role can only see its assigned jobs and never reads `leads` table directly in UI.
4. Customer tracking `/track/?token=<64 hex>`: token is 32 random bytes; database stores SHA-256 only. Public page reveals case code, title, owner-managed status, update date, no personal data or rates. Link possession grants limited access; rotate link on exposure.
5. Private server root sibling `<domain-root>/secure/`: `aaditechs-config.php`, PHPMailer Composer vendor, filesystem rate-lock directories, and CLI-only retention job. Not reachable via web server.

## Database

- `leads`: consent-based customer inquiry details. Do not accept passwords, confidential files or sensitive device data.
- `partners`: active flag, display name, specialty, email login and password hash. Password is never stored plaintext.
- `jobs`: lead relationship, assigned partner, owner quotation, partner cost, status, SHA-256 tracking token hash. No automatic financial calculations or promises.
- `job_activity`: role-scoped owner/partner update and audit notes (never public).

## Workflow

Lead intake → admin qualifies → creates case and optionally assigns partner → partner updates assessment/work state → admin reviews quote/cost and obtains customer approval outside platform → admin marks approved → partner performs → owner closes → private tracking page reflects status. The portal does not infer approval or send actual payment/contract requests.

## State flags

- `assets/site-config.js` `leadSubmission=false` means homepage/chat/AV builder never POST; they clearly show demo mode.
- `secure/aaditechs-config.php` `leads.enabled=false` means backend rejects writes even if public flag is accidentally enabled.
- `portal.enabled=false` blocks private portal and tracking, independent of public flags.
- `google.enabled=false` and `googleReviewsApi=false` retain the genuine-review integration but do not call Google API or display fictitious ratings.

## Security caveats & tradeoffs

- This is a **single-owner lightweight portal**, not a fully audited enterprise IAM or billing system. HTTPS, firewall/CDN rules, two-factor support and security review recommended before handling substantial customer data.
- Tracking tokens in URL can appear in web-server logs and browser history; set no-referrer/noindex, instruct customers to keep private, rotate when exposed; use authenticated customer accounts if stronger privacy is required.
- Per-IP file rate limiting is basic abuse prevention, not a WAF or CAPTCHA substitute. Add provider-level abuse control for public launch.
- `privacy/` is **draft**, must be adapted by owner/legal counsel before enabling live leads.
- Lead notifications are best effort; database and owner queue are authoritative. Avoid automated unsolicited marketing.
- Google OAuth approval independent of this app and may be denied; do not bypass it.

## Dependencies

- PHP >= 8.1, PDO MySQL, MySQL/MariaDB, TLS; `mbstring` optional fallback for validation, `openssl` for secure sessions/randomness.
- Composer + PHPMailer for optional SMTP; cURL required for existing Google Reviews endpoint.
- JS browser support: modern evergreen browsers, no external JS framework.
