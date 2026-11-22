# Aaditechs — Verified progress / fresh-agent handoff

**Source version:** V7, implemented in project code 23 November 2026. Previous V6 GitHub baseline SHA `43bba82c0d75fb842c1e928eec5d9aa85ab7dbf0`. This file describes source completeness, **not production uptime**. Read the repository and confirm the actual Git HEAD before coding or deploying.

## Implemented in source

- [x] Original orange-and-blue logo retained; IT and Data Recovery service categories preserved.
- [x] AV division homepage, filters, dedicated Video Conferencing, Auditorium AV and Professional Sound/PA pages.
- [x] AV project scope builder and honest no-fake-pricing copy.
- [x] Guided English/Hinglish chatbot for Data Recovery, AV, IT Support/AMC, Software and Hardware, with initial triage and consent-based inquiry. Deterministic logic, **not** a connected LLM or live support.
- [x] Hostinger MySQL schema: leads, partners, jobs, job_activity.
- [x] Hardened lead API: validated JSON, consent, HTTPS/same-origin checks, per-IP salted hash rate-limit and database-first persistence.
- [x] Owner dashboard: authenticated lead list, lead status, partner accounts/password reset/disable, work-order assignment, quote/cost fields and audit notes.
- [x] Partner login: assigned work only, limited status updates, no customer contact or owner financial details.
- [x] Customer case status: hashed random token, noindex, minimal fields, manual sharing; rotate invalidates older links.
- [x] Secrets template, schema, runbook, private storage documentation, sitemap/robots and offline browser/PHP lints.

## External dependencies / production blockers — not completed

- [ ] Confirm exact Hostinger account PHP version, PDO MySQL, SSL, writable private sibling folder, backups, DB access and Composer/PHPMailer availability.
- [ ] Privacy notice is a **DRAFT**; verify business data controller details, storage/retention, partner sharing and India DPDP requirements with owner/advisor.
- [ ] Create actual database on Hostinger, import `secure-setup/schema.sql`, set restricted DB account credentials in private config.
- [ ] Generate strong admin passphrase hash and secure partner onboarding. Run auth/session, CSRF, role access and logout tests over actual HTTPS.
- [ ] Configure SMTP and verify accepted notification, delivery and bounce behavior; never assume email is delivered just because SMTP accepted.
- [ ] Configure `retention_days` after policy review and schedule CLI cleanup; audit jobs before any deletion.
- [ ] Turn on feature flags (`database.enabled`, `leads.enabled`, `portal.enabled`, public `leadSubmission`) only after staging tests.
- [ ] Google Business Profile API: on 18 Nov 2026 the application was stopped at the 60-day eligibility criterion. Earliest cautious retry around 5 Dec 2026 if verification was on 4/5 Oct. Google's approval is still required; keep `google.enabled=false` and `googleReviewsApi=false` until then.
- [ ] Verify Google profile, business address, real phone numbers, service capabilities, rates and partner SLAs with owner before publishing.
- [ ] Hostinger **staging and production website** not deployed in this code task. Preserve existing production `aaditechs.in`; back up before any replacement.
- [ ] True LLM assistant (optional Ollama inference hosting) is not implemented; requires a separately secured inference service and privacy controls.
- [ ] Payment processing, real-time partner booking, automatic quotes, customer self-approval, ERP and Google analytics connectors are NOT implemented. These require separate confirmed requirements.

## Important decisions

- Aaditechs is a **coordinated IT + AV service partner network**; works with separate specialists for data recovery, software, hardware procurement, field IT support and audio visual. Never pretend all work is delivered directly by full-time Aaditechs employees.
- Owner controls final price/partner cost and customer commitments. Partner portal displays assigned work scopes but NOT customer contact fields or internal pricing.
- User explicitly asked to push complete code to `mohdazizshaikh/aaditechsin.in` via GitHub identity `mohd.aziz.sk@gmail.com`. Repository is **public**. Never push credentials or real lead exports.
- Google auth keys, SMTP passwords and database credentials must live outside `public_html` and Git.

## Source verification

- Run PHP/JS syntax and `tests/browser_smoke.py`; check all links and frontend demo-mode behavior.
- Confirm Git tree SHA and every changed file's Git blob SHA matches local files after push; avoid force pushes.
- Before any live deploy: reproduce staging setup; perform negative tests with public flags off; back up production and rollback assets.

## Next action for future AI agent

Start from this repo's latest HEAD; read `README.md`, `README_DEPLOYMENT.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_CHECKLIST.md`, and this status. **Do not claim Hostinger deployment, OAuth approval, email delivery or database tests without evidence.** Obtain infrastructure access/credentials securely; do not request secrets in chat. Introduce new coding functionality only against a verified backlog and preserve existing service content/logo.
