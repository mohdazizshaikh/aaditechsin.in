# Aaditechs preproduction security & data handling checklist

> For **proposed** AI chat, marketing, paid ads, campaign approvals and follow-up security requirements, consult [V9 safety and approval gates](V9/05_SECURITY_PRIVACY_APPROVALS.md). Those V9 safeguards remain design requirements, not passing production checks.

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
