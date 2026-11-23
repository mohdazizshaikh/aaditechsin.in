# Aaditechs — Verified progress / fresh-agent handoff

## V9 branch implementation (23 November 2026) — source-only feature branch

V9-02 has begun as an **undeployed deterministic qualification guide** on `feature/v9-growth-engine`: seven named public service playbooks (IT AMC, VC, Auditorium AV, Professional Sound, Data Recovery, Hardware, Software), page-aware chat selection, edited/skip answers, review-before-consent and preserved V8 inquiry fallback. Added `tests/v9_service_playbooks.test.js`, an optional headless-browser suite, and feature-branch source-only CI trigger. Details, evidence and remaining gates: [V9-02 implementation](V9/10_V9_02_SERVICE_GUIDE_IMPLEMENTATION.md).

**V9-03 second isolated source slice:** added `secure-setup/migrations/2026-11-23-v9-001.sql` (five additive tables), `public_html/api/v9/lead_rules.php` (pure validation, salted hashes, safe attribution), and an independently disabled `public_html/api/v9/leads.php` (transactional lead/consent/events and idempotent replay). V8 `/api/lead.php` and public form routing remain unchanged. Private config defaults OFF. Source-only Node/Python tests pass; PHP lint and rules are pending environment/CI evidence; no V9 SQL applied. See [V9-03 implementation](V9/11_V9_03_LEAD_FOUNDATION.md).

The V8 CLI `prune.php` now conservatively detects an all-or-nothing V9-001 schema. With V9 installed, it skips leads carrying audit/consent/request records; partial V9 schema blocks pruning rather than deleting around foreign keys. This is source-only and has **not** been run against a database. Full retention and idempotency expiry cleanup still require owner/legal policy and staging tests.

**Verified source CI on V9-03 baseline:** [run 45087157567](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45087157567) succeeded on commit `88672a97532f5bf344a061d57ce5d7ec0454f6b5`, including PHP lint, PHP pure rules, JS checks, Python security/links and HTTP source smoke. This **does not** validate the later prune compatibility patch until its own feature-branch CI run completes, and never substitutes for MySQL/Hostinger staging.

**Tests observed here:** Node playbooks PASS; JavaScript syntax PASS; Python feature/security and V9 migration source checks PASS. Local PHP interpreter and `bs4` are absent; headless Chromium test could not launch under socket sandbox policy. These are **BLOCKED**, not successes. No real-browser, Hostinger, database, SMTP, LLM or production verification. V9-02 and V9-03 acceptance remain **partial**. `main`, production feature flags and live site untouched. Confirm actual remote commit SHA against GitHub, do not assume this document is push proof.

## V9 branch planning (23 November 2026) — historical documentation milestone

**Latest V9-01 follow-up:** Read-only public-site inspection and Hostinger connection test were completed; authenticated Hostinger inspection is **BLOCKED** by missing integration and a read-only execution-environment config path. See [V9 staging readiness](V9/09_STAGING_READINESS_2026-11-23.md). No staging/database/SMTP/production change was performed, and live public service details still need owner verification.

- **Confirmed verified V8 GitHub commit:** `a551761be4846eba905a4461e7a416d1389c2ef1` on `mohdazizshaikh/aaditechsin.in` `main` as inspected before branching.
- **New branch:** `feature/v9-growth-engine`, created from the exact V8 commit, to isolate the proposed revenue/growth engine from production source.
- **New planned documentation:** [V9 Start Here](V9/00_START_HERE.md) links eight V9 planning documents covering product, architecture, M01–M10 modules, schema/API contracts, privacy/approvals, deployment/rollback, testing, backlog and owner decisions.
- **Actual V9 implementation:** NOT STARTED. No `/api/v9/` endpoints, migrations, live AI provider, auto-marketing, automatic ads or revenue reporting are included in this planning milestone. Do not mark these features complete or imply a Hostinger deployment.
- **Git push verification:** record the actual V9 branch commit SHA after commit and verify remote tree; this document alone does not assert a push happened.
- **Unchanged blockers:** V8 live Hostinger staging, private MySQL/SMTP tests, owner/legal privacy review, Google Business Profile API approval, service/pricing facts and explicit live cutover consent.

Keep V8 source status below unchanged as historical baseline. Each V9 implementation commit must update this section with evidence rather than overwriting it with aspiration.

**Source version:** V8 gap-audit remediation implemented in project code 23 November 2026. Previous V7 SHA `ea67fa26c558d01640139ac5eecd00c5c2a45623`. Previous V6 GitHub baseline SHA `43bba82c0d75fb842c1e928eec5d9aa85ab7dbf0`. This file describes source completeness, **not production uptime**. Read the repository and confirm the actual Git HEAD before coding or deploying.

## V8 audit additions (local source, not live acceptance)

- [x] Partner cannot start `in_progress` without owner-recorded customer approval; assessment-only notes now work.
- [x] Admin approval evidence method/reference and a positive quoted amount are enforced server-side; staged progression controls and audit events added.
- [x] Partner password reset/disable invalidates live sessions on next request; owner admin hash changes invalidate owner sessions.
- [x] Customer tracking token TTL and origin validation; preexisting V7 DB requires documented V8 schema migration.
- [x] New standalone Data Recovery and IT AMC pages; local service routes and sitemap updated.
- [x] `tests/workflow_rules.php`, `tests/site_integrity.py`, `tests/http_smoke.py` plus prior browser/static tests; not substitutes for Hostinger DB end-to-end acceptance.
- [x] Updated V8 architecture, security checklist, deployment runbook and gap audit; no real secrets or user records in source.

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

## Still-open gaps after V8 fixes

- Hostinger staging and live PDO MySQL/SMTP/role tests not performed. Privacy page is still a DRAFT.
- No real Google OAuth integration approval, interactive LLM, 2FA or automatic financial/customer approval workflow.
- Owner must confirm actual business contact/address and policy, partner availability and service SLAs.
- Public Git repository contains templates only; no real credential files.
- Reference `docs/GAP_AUDIT_V8.md` for exact source fixes and negative tests.

## Next action for future AI agent

Start from this repo's latest HEAD; read `README.md`, `README_DEPLOYMENT.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_CHECKLIST.md`, and this status. **Do not claim Hostinger deployment, OAuth approval, email delivery or database tests without evidence.** Obtain infrastructure access/credentials securely; do not request secrets in chat. Introduce new coding functionality only against a verified backlog and preserve existing service content/logo.

## 23 Nov 2026 staging continuation: source-only preflight

- [x] Separate V8 maintenance work on `main` re-ran PHP/JavaScript syntax, workflow, security, local-HTTP and browser smoke suites (source/offline; did not test live hosting).
- [x] Added server-side CLI preflight (`secure-setup/maintenance/preflight.php`) with an optional **read-only** DB check; avoids revealing secrets and fails closed on missing live configuration.
- [x] Added GitHub read-only `source-validation.yml` checks, plus `docs/STAGING_ACCEPTANCE.md` with specific test evidence and no-premature-production release gates.
- [ ] Hostinger authenticated integration not available to that run; **no staging/production Hostinger deployment, DB creation or SMTP delivery took place**. This must be verified separately once a Hostinger session is connected.
- [x] CI first run identified missing `beautifulsoup4` in the clean runner; workflow now installs a pinned dependency in an isolated venv. This is CI-only environment remediation, not live Hostinger acceptance.

**V9 branch reconciliation:** V9 design must retain the above `main` changes when merged. Source status is branch-specific; do not treat the preflight checks as proof of a deployed V9 module.
