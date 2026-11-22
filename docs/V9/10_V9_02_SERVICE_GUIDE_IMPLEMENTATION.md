# V9-02 — service qualification source milestone

**Status:** source implementation for review; **NOT deployed, NOT live-tested on Hostinger, NOT production lead intake**. Recorded 23 November 2026. See [staging blockers](09_STAGING_READINESS_2026-11-23.md).

## Exact implemented scope

- Single public configuration file `public_html/assets/service-playbooks.js` supplies seven allowlisted codes: `IT_AMC`, `VC`, `AUD_AV`, `SOUND`, `RECOVERY`, `HARDWARE`, `SOFTWARE`.
- Each playbook has its approved-scope *draft* name, safe non-guarantee introduction, local service destination and 3–4 questions. The owner still must approve geographic claims, contact details, partner scope and wording before release.
- `public_html/assets/chat-v2.js` renders the playbooks; specific terms route to Video Conferencing, Auditorium AV, Sound etc. Unknown requests leave the visitor in the explicit service menu. Landing on IT AMC, VC, Auditorium, Sound or Recovery service pages preselects that page's guide **when the visitor opens chat**.
- Questions support “Skip this question” and “Change service”. After completion the visitor sees a summary and can edit individual answers before proceeding.
- The chat is **deterministic**; no LLM, CRM scoring, autonomous outreach, pricing or partner dispatch is connected. Text is inserted with `textContent`, not HTML. A warning discourages secret/confidential input.
- Answer length is capped at 300 characters per question so even the longest four-question playbook summary remains within the existing V8 lead API's 2,000-character message limit.
- Consent is still required in the existing lead form. Until `site-config.js` `leadSubmission` is changed **after** authorized staging acceptance, submitting reports *demo mode* and does not send/store any customer record. The existing `api/lead.php` is unchanged, as are its hardening, storage and server settings.
- All seven HTML entry points that load the chat now load `service-playbooks.js` before `chat-v2.js` with `defer`. The original logo, AV planner, existing pages, V8 partner/owner roles, and all OFF feature flags remain unchanged.

## Boundaries and interfaces

| Boundary | Current behavior | Future work |
|---|---|---|
| Public playbooks | Safe JS config loaded with chat on relevant pages | Owner-approved copy/localities; optional versioned server delivery |
| Intent detection | First matching ordered word/phrase rule, explicit menu fallback | More language examples and owner-reviewed terms |
| Assessment answers | Ephemeral browser state before consent; no chat transcript API | Schema-validated structured output in V9-03, with minimization |
| Lead submission | Existing `/api/lead.php`, only if dual server/public flags intentionally enabled later | V9-03 idempotency, canonical service code and event/consent migration |
| AI generation | OFF; none present | V9-09 optional protected adapter with deterministic fallback |
| Partner quote/dispatch | Never triggered by the chat | V9-06 owner-approved workflows |

**Design choice:** No extra PHP route or duplicated JSON catalogue was introduced for the source-only pilot. The JS playbook is presentation data, **not server authorization**. In V9-03, the server must accept only canonical `service_code` values and verify them independently; it must not trust this browser catalogue for authorization or lead classification. Until then V8 receives the existing human-readable `service` string.

## Reproducible checks

Run from repository root:

```sh
node tests/v9_service_playbooks.test.js
for file in public_html/assets/*.js; do node --check "$file" || exit 1; done
python3 tests/security_static.py
python3 tests/site_integrity.py
node tests/v9_chat_browser.test.js
```

`tests/v9_service_playbooks.test.js` uses Node built-ins only and covers seven code shapes, route samples, unknown input, 3–5 prompts, summary maximum length, consent/demo controls, review editing, and all script load order. The GitHub source-only CI now runs this test on the V9 feature branch. `tests/v9_chat_browser.test.js` is an **optional** Playwright/Chromium smoke suite that exercises mobile flows, consent denial, editing, demo fallback, auto-selection and closing. Requires a launchable Chromium and installed Playwright; it is not mandatory CI evidence until runner setup is reviewed.

**Execution record (23 November, source-only container):** Node playbook contract **PASS**, JS syntax **PASS**, Python static security/feature flags **PASS**. The `site_integrity.py` run was **BLOCKED by missing `bs4`** locally (the CI workflow explicitly installs pinned `beautifulsoup4`), and optional Chromium browser smoke was **BLOCKED by environment socket restrictions** (`socket() failed: Operation not permitted`), not a verified browser pass. PHP/MySQL/SMTP, mobile device testing and Hostinger staging remain **NOT TESTED**. Do not convert these to passing gates without evidence.

## Remaining acceptance before V9-02 is marked complete

1. Read-only review of actual business service scope, contact details and privacy notice by owner.
2. Run browser mobile/keyboard/T02 paths in a permitted test runner; test form validation and network failure after explicit, safe staging config.
3. Run canonical links/integrity and full V8 role/security regression suite in CI and check results.
4. Complete postponed V9-01 staging/backup; do not activate public lead flags earlier.
5. Record a rollback-ready Hostinger deployment decision separately. This source commit contains **no deployment**.

Next independent coding item is **V9-03 server-side lead safety foundation**, still behind a staging-migration/owner-consent gate. Keep migrations additive and do not alter production data from this branch.
