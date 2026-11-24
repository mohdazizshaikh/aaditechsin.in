# Aaditechs — Verified progress / fresh-agent handoff

**Historical V8 baseline:** V8.5 code and disposable-MariaDB tests verified on 23 November 2026; see current GitHub `main` for the exact latest commit. Previous V7 SHA `ea67fa26c558d01640139ac5eecd00c5c2a45623`. Previous V6 GitHub baseline SHA `43bba82c0d75fb842c1e928eec5d9aa85ab7dbf0`. This file describes source completeness, **not production uptime**. Read the repository and confirm the actual Git HEAD before coding or deploying.

## Current development baseline — 24 November 2026

Production is unchanged. Main remains V9.6 at verified commit 7582be3c9beafa7335c1ced9bc9c6369b4360daf (CI 45380755788). V9.7 onward are stacked unmerged development PRs. Verify current Git HEAD and CI before proceeding; this is a dated evidence snapshot, not a live status feed.

| Milestone | Source / evidence | Remaining |
|---|---|---|
| V9.1–V9.6 | [Main consolidated CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45380755788) | Live provider, owner bot, privacy and staging acceptance |
| V9.7 | [PR #3](https://github.com/mohdazizshaikh/aaditechsin.in/pull/3), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45443108590) | HTTPS endpoint, local Ollama runtime, scheduling |
| V9.8 | [PR #4](https://github.com/mohdazizshaikh/aaditechsin.in/pull/4), [corrected CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45447300543) | Real diffusion, native video, automatic transfer and binary preview |
| V9.9 | [PR #5](https://github.com/mohdazizshaikh/aaditechsin.in/pull/5), [corrected CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45449569981) | Live AI, consented free text, customer-specific qualification |
| V10.0 | [PR #6](https://github.com/mohdazizshaikh/aaditechsin.in/pull/6), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45449791408) | Live SEO crawl, Search Console, competitor evidence, scheduling |
| V10.1 | [PR #7](https://github.com/mohdazizshaikh/aaditechsin.in/pull/7), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45450797137) | Facebook/Threads live authorization, Instagram/media transport, token lifecycle and insights |
| V10.2 | [PR #8](https://github.com/mohdazizshaikh/aaditechsin.in/pull/8), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45452061745) | LinkedIn/X/GBP text adapters, unified reporting; live OAuth and media uploads pending |
| V10.2A | [PR #9](https://github.com/mohdazizshaikh/aaditechsin.in/pull/9), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45453713017) | Manual private SFTP, verified MP4 staging, immutable YouTube upload approval and mock provider flow; actual OAuth, Instagram and real uploads pending |
| V10.2B | [PR #10](https://github.com/mohdazizshaikh/aaditechsin.in/pull/10), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45454207277) | Direct Reel upload and separate processing/publish gates, real provider/media format acceptance pending |
| V10.2C | [PR #11](https://github.com/mohdazizshaikh/aaditechsin.in/pull/11), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45454875369) | Facebook photo/video direct multipart, exclusive revision reservation, local media decode validation; live acceptance pending |
| V10.2D | [PR #12](https://github.com/mohdazizshaikh/aaditechsin.in/pull/12), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45455600163) | LinkedIn/X single PNG upload and attachment; video and live acceptance pending |
| V10.2E | [PR #13](https://github.com/mohdazizshaikh/aaditechsin.in/pull/13), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45456657400) | Threads/Instagram single JPEG, short-lived bearer delivery and explicit publish, 4:5 IG derivative and positive core fetch tests; live endpoint/log redaction, carousels, video pending |
| V10.2F | [PR #14](https://github.com/mohdazizshaikh/aaditechsin.in/pull/14), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45457164812) | Owner-only preview, bounded inventory, reversible old-orphan quarantine and audit; live browser/storage acceptance and policy purge pending |
| V10.2G | [PR #15](https://github.com/mohdazizshaikh/aaditechsin.in/pull/15), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45458155252) | Local expiry/revocation gates and sanitized read-only health, manual rotation; automatic OAuth refresh/consent pending |
| V10.2H | [PR #16](https://github.com/mohdazizshaikh/aaditechsin.in/pull/16), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45458986812) | Durable intermediate LinkedIn/X image receipts and reconciliation report. Video/carousel adapters still pending |
| V10.2H.1 | [PR #17](https://github.com/mohdazizshaikh/aaditechsin.in/pull/17), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45460232162) | LinkedIn multipart video, durable Reel container, expanded recovery report and storage protection; live acceptance and X/Threads video/carousels pending |
| V10.2H.2 | development/v102h2-video-batch source; CI pending | X chunked MP4, Threads expiring MP4 fetch, exact type approval and recovery; carousels and live provider acceptance pending |

Priority backlog: P0 source CI and authorization; P1 verify private media staging and YouTube upload, Instagram direct Reel runtime acceptance, Facebook media runtime, Threads/X/LinkedIn video runtime, Instagram and Threads carousels, bearer endpoint acceptance, private preview/retention runtime, referenced asset retention policy, actual OAuth consent, refresh/revocation, alerts and provider acceptance; P2 communications and opportunity feeds, dashboard; final isolated staging and production release gate. Do not mark full social integration complete until media, OAuth, live receipts and reconciliation accepted.

Next phases: V10.3 communications, V10.4 discovery providers, V10.5 dashboard, V10.6 staging and release. Native AI video, actual local model tests, public AI and provider acceptance remain open independently. No real outreach, posts, production changes or credentials in Git. All new flags OFF.

Canonical tracker: [issue #2](https://github.com/mohdazizshaikh/aaditechsin.in/issues/2); [release gate #1](https://github.com/mohdazizshaikh/aaditechsin.in/issues/1). Module docs V9_GROWTH_FOUNDATION, V92_ANALYTICS, V93_DISCOVERY, V931_SOURCE_ADAPTER, V94_CREATIVE, V95_V96_OPERATIONS, V97_CREATIVE_TRANSPORT, V98_MEDIA_STUDIO, V99_TECHNICAL_EXPERT, V100_SEO, V101_META, V102_NETWORKS, V102A_MEDIA_UPLOAD, V102B_INSTAGRAM, V102C_FACEBOOK_MEDIA, V102D_NETWORK_MEDIA, V102E_META_IMAGES, V102F_MEDIA_STORAGE, V102G_OAUTH_HEALTH, V102H_MEDIA_RECOVERY, V102H1_VIDEO_BATCH, V102H2_VIDEO_BATCH.

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

- [x] Re-ran V8 PHP/JavaScript syntax, workflow, security, local-HTTP and browser smoke suites (source/offline tests passed).
- [x] Added server-side CLI preflight (`secure-setup/maintenance/preflight.php`) with an optional **read-only** DB check; avoids revealing secrets and fails closed on missing live configuration.
- [x] Added GitHub read-only `source-validation.yml` checks, plus `docs/STAGING_ACCEPTANCE.md` with specific test evidence and no-premature-production release gates.
- [ ] Hostinger authenticated integration not available in the current execution environment; **no staging/production Hostinger deployment, DB creation or SMTP delivery took place**. This must be verified separately once a Hostinger session is connected.

- [x] CI first run identified missing `beautifulsoup4` in the clean runner; workflow now installs a pinned dependency in an isolated venv. This is CI-only environment remediation, not live Hostinger acceptance.


## V8.2 source security refinement (pending hosting verification)

- Added optional native PHP owner TOTP (RFC 6238) using an offline/private base32 secret and negative/known-answer tests. **Default OFF** until owner enrollment; partner MFA not yet implemented.
- Removed potentially sensitive free-text job title from customer tracking page; public token shows only minimal status fields.
- Regenerated anonymous CSRF token on successful sign-in.
- Added local/CI tests, private-config preflight checks and deployment instructions. Hostinger staging, real SQL, SMTP, recovery and OAuth approval still pending.

## V8.3 disposable integration test milestone (23 November 2026)

- [x] Added a second GitHub Actions job with a disposable MariaDB 10.11 service, PHP PDO MySQL and a self-signed local TLS test proxy, separate from existing offline source checks.
- [x] Added `tests/integration_mysql_http.py` and a localhost-only, test-only HTTPS simulation router. Tests use **synthetic** contacts, generated owner password hash and a temporary ignored private configuration; this router is NEVER deployed.
- [x] Script covers lead consent/origin/duplicate rate limit, SQL persistence, owner/partner login and session isolation, job creation, blocked pre-approval partner start, recorded owner approval, partner completion, disabled-partner session revocation, customer tracking privacy and expiration.
- [ ] GitHub-hosted integration run must pass on the latest commit before marking these new database/HTTP tests successful. Even when green, it does NOT prove Hostinger staging is deployed, authenticated, backed up or able to deliver email.
- [ ] Hostinger connector/privileged staging access still required for private config, real hosted PDO MySQL, authenticated HTTPS, SMTP delivery, backup/rollback and privacy signoff.


## 23 Nov 2026 V8.4 code-only continuation

- [x] Added transactional lead + optional notification intent persistence and private CLI retry worker (default no SMTP operations without `--run` and private flags).
- [x] Added `notification_outbox` to full SQL schema and a separate additive V8.4 migration.
- [x] Added owner-only delivery counters and manual requeue for failed alerts.
- [x] Extended synthetic MariaDB CI to verify enqueue, dry-run no-write behavior, and owner dashboard summary.
- [ ] **Not deployed:** Hostinger cron installation, real SMTP acceptance/delivery/duplicate handling, production privacy approval and actual backups remain external blockers.

## V8.5 retention fail-safe — source implementation

- [x] CLI retention task is **dry-run by default**; requires explicit `--run` for deletion; invalid arguments fail closed.
- [x] Pending/sending/dead owner alerts and all assigned cases are excluded from lead cleanup.
- [x] Raw DB/PHP exception content no longer printed to cron stderr.
- [x] Disposable MariaDB integration test covers dry-run, explicit delete and protected rows.
- [ ] Real Hostinger retention, restore and scheduled deletion still **not accepted**; staging evidence and privacy review required.
- [x] GitHub Actions run [45197506078](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45197506078) completed successfully: offline source checks and disposable MariaDB integration both passed. This is **not** Hostinger, SMTP or recovery acceptance.

## V8.6 notification failure-path test hardening (source-only)

- [x] Added disposable PHPMailer test double to synthetic MariaDB workflow: six failed attempts, retry backoff/terminal state, authenticated owner requeue and eventual fake success. No network SMTP delivery.
- [x] GitHub CI passed for V8.6 notification failure-path extension on commit `a47ea9121ed91ee254bb755de0fdd922fd7fc05f`; offline checks and disposable MariaDB integration both passed. Run: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45209185309
- [ ] Hostinger staging, real SMTP inbox delivery, backup/restore, privacy authorization and production release remain pending.

## V9.1 growth foundation (source-only)
- Added 14-service registry, review-only task tables and audit, version-checked approve/reject/regenerate request, paused control default, owner-only Telegram webhook source and private CLI draft intake.
- Growth and Telegram flags default OFF. No bot provisioned or webhook registered, no real publishing/outreach, no analytics or AI generator. Verify the new offline and disposable MariaDB CI jobs, then stage separately. See docs/V9_GROWTH_FOUNDATION.md.

## V9.2 optional website analytics (source only)
- V9.1 final CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45354481922.
- Added consent-gated first-party event collector, strict event API, day-scoped tab-session estimates, service interactions, broad traffic channels, click/automation-signal counts, IST saved-lead reports, Telegram query commands and dry-run retention CLI.
- Both analytics flags default OFF. No Hostinger collection, real bot delivery, GA4/Search Console, actual call/mail/message data, campaign-to-lead attribution or social connectors. Validate latest CI and separately perform browser/staging acceptance. See docs/V92_ANALYTICS.md.

## V9.3 evidence-backed opportunity and outreach review (24 Nov 2026)
- Added private CLI import of public/authorized source evidence, all 14 service matching rules, date/URL validation, deterministic score and duplicate control. No network fetching or personal-contact enrichment. Imported evidence requires owner verification.
- Added Telegram opportunity list/detail, qualify/dismiss, pause-gated creation of one linked outreach draft, and draft inventory. V9.1 versioned approval remains review-only; no email or message sender.
- Added additive migration 2026-11-24-v93.sql, independent discovery flag default OFF, preflight and synthetic rules/database tests. Initial CI schema split failed on a SQL comment semicolon; corrected in commit 630af823333bf0f577607b5a65ca7a6a0511aeec. CI run: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45377527089 (status at documentation write: in_progress/pending).
- Still pending: authorized external source adapters, actual contact permissions, message/call/reply records, lead conversion, Telegram test bot, Hostinger and retention approval. See docs/V93_DISCOVERY.md and issue #2. No production change.

## V9.3.1 authorized evidence feed adapter (source only)
- Added private CLI HTTPS JSON transport, explicit source authorization, public IPv4 pinning, size/timeout limits, no redirects, service matching and source-run ledger. Dry-run validates configuration without network; explicit run checks growth pause before each source. /discoveryruns reports bounded import/error counts.
- No actual provider configured or contacted. Synthetic test covers feed contract, destination checks, duplicate/partial/failure accounting and owner report; verify final CI. See docs/V931_SOURCE_ADAPTER.md. Live provider/API acceptance, source retention and real outreach remain pending.

## V9.4 catalog-based local AI creative workflow (source only)
- V9.3.1 CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45378963848.
- Added nine channel formats, four objectives, 14-service catalog-only export, local loopback Ollama structured-output worker, caption/visual/video briefs, versioned result import/history and Telegram /createcontent /content /coverage. Pause and approval gates preserved. No publish connector or rendering.
- Creative flags OFF. Local model and actual bot not connected. Synthetic tests validate input/claims, fake structured outputs and database revisions; final CI needs verification. See docs/V94_CREATIVE.md. No production changes.

## V9.5–V9.6 source milestones (24 Nov 2026)
- V9.4 synthetic creative CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45379962242. Actual model untested.
- V9.5 text-only Telegram business channel scheduling, exact approved revision/destination, private worker, receipt/error states and unknown no-retry. Synthetic CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45380253444. Other social adapters not implemented.
- V9.6 owner daily snapshots and completed-hour error digests, independent notification preferences, private owner bot destination, period deduplication, provider receipts and report history. No live bot delivery or cron. Final CI needs verification.
- See docs/V95_V96_OPERATIONS.md for full activation order and remaining gaps. All new external feature flags default OFF. No production changes.
