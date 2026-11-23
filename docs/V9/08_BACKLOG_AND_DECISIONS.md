# V9 delivery backlog, dependencies and fresh-agent handoff

**Status:** actionable plan with **source-only V9-02/V9-03 pilots**, not a claim of deployed functionality. New branch `feature/v9-growth-engine` isolates V9 work; V8 `main` remains stable until reviewed merge. See [V9-02](10_V9_02_SERVICE_GUIDE_IMPLEMENTATION.md) and [V9-03](11_V9_03_LEAD_FOUNDATION.md) for implementation and test evidence. Use **small reviewable commits and migrations**, not one huge implementation.

## 1. Ordered backlog — one person can operate it

| ID | Priority | Work package (scope deliberately small) | Dependencies | Acceptance gate |
|---|---|---|---|---|
| V9-00 | P0 | Establish V9 documentation branch, link all source truth, confirm V8 tests and main SHA | Verified V8 archive + repo | Docs complete; no V9 live claims |
| V9-01 | P0 | Finish real V8 HTTPS staging, DB/mail/owner+partner acceptance and privacy text | Authorized Hostinger session/legal copy; **BLOCKED as of 23 Nov 2026**, see [readiness report](09_STAGING_READINESS_2026-11-23.md) | [V8 runbook](../../README_DEPLOYMENT.md) evidence; **blocks all V9 live intake** |
| V9-02 | P0 | Service playbook config + mobile intake/qualification UX for IT AMC/VC and 5 other services | Source pilot built; V9-01/owner signoff deferred | Node contracts PASS; browser/Hostinger acceptance pending — **PARTIAL** |
| V9-03 | P0 | Add migration V9-001 CRM consent/event/attribution and OFF-by-default retry-safe intake | Source built; V9-01/backup/PHP/DB gates deferred | Static tests PASS; PHP lint/transaction/replay/staging still pending — **PARTIAL** |
| V9-04 | P0 | Owner lead inbox and scoring/next-action task UI | V9-03 | Role tests, audit and pending-lead visibility |
| V9-05 | P0 | M04 owner task reminders + email outbox, contact consent and suppression | V9-03/04, SMTP validation | T04-1..3, kill switch OFF by default |
| V9-06 | P1 | Partner quote scope draft and existing approval gate UX; actual cost capture | V9-01, V9-04 | M05 V8 regression + T05-* and no auto-authorize |
| V9-07 | P1 | Owner profit dashboard: estimated vs collected/reconciled, job financial migration | V9-06 | T09-*; independent finance evidence |
| V9-08 | P1 | Search Console/GA4 measurement and first-party SEO content queue | Verified Search Console/GA4 and privacy, V9-02 | M01/M07 source/claim tests and owner publish control |
| V9-09 | P2 | Optional secured Ollama adapter for draft and extraction | V9-02/03, private provider access and data terms | T02-2/T10-*; fallback and cost limit proven |
| V9-10 | P2 | Opportunity watchlist with owner-entered/permitted sources | Service coverage confirmed, source terms reviewed | T06-*, zero unsolicited outreach |
| V9-11 | P2 | Social publishing and review-response draft calendar | M07, verified platform API permissions | Owner approval + provider success; no fake reviews |
| V9-12 | P2 | Paid ad recommendation/reporting dashboard (initially READ-ONLY) | Budget choice, verified account, tracking | T08-*, no write permission by default |
| V9-13 | P2 | Controlled, one-time owner-approved advertising API mutation (OPTIONAL) | V9-12 + independent security/account review | Budget/geography bound to approval, kill switch and spend audit |
| V9-14 | P2 | Production rollout + 30-day outcome review | Relevant preceding slices and owner signoff | Staging evidence, rollback run, actual profit metrics |

## 2. Release slices

**Slice 0 — Foundation:** V9-00 + V9-01 (proof V8 can really run on Hostinger).  
**Slice 1 — Lead capture:** V9-02 through V9-04 (actual useful leads, no LLM required).  
**Slice 2 — Conversion:** V9-05 through V9-07 (faster response, owner quotes, real margin).  
**Slice 3 — Organic acquisition:** V9-08 + optional V9-10/11 (publish useful approved content and prospect watchlist).  
**Slice 4 — Optional AI/Ads:** V9-09, V9-12, V9-13 only after measured need and independent approvals.

Each slice must be useful standalone; **do not block forms or quotations because Google Business Profile OAuth or Ollama is missing**.

## 3. Traceability: requirements → tests → backlog

| Module | Source specification | Required tests | First backlog |
|---|---|---|---|
| M01 Acquisition | [03](03_MODULE_SPECS.md#m01--acquisition-local-seo-and-service-conversion) | T01-1/2 | V9-02, V9-08 |
| M02 AI chat | [03](03_MODULE_SPECS.md#m02--ai-assisted-chat-and-qualification) | T02-1/2/3 | V9-02, V9-09 |
| M03 CRM | [03](03_MODULE_SPECS.md#m03--crm-triage-lead-scoring-and-owner-inbox) | T03-1/2/3 | V9-03/04 |
| M04 Follow-ups | [03](03_MODULE_SPECS.md#m04--follow-ups-appointment-requests-and-messaging-outbox) | T04-1/2/3 | V9-05 |
| M05 Partner jobs | [03](03_MODULE_SPECS.md#m05--quotations-partner-orchestration-and-customer-progress) | T05-1/2/3 | V9-06 |
| M06 Opportunity discovery | [03](03_MODULE_SPECS.md#m06--opportunity-discovery-and-b2b-watchlist) | T06-1/2 | V9-10 |
| M07 Content | [03](03_MODULE_SPECS.md#m07--content-social-media-and-review-support-desk) | T07-1/2 | V9-08/11 |
| M08 Ads | [03](03_MODULE_SPECS.md#m08--paid-campaign-assistant-and-spend-control) | T08-1/2 | V9-12/13 |
| M09 Profit | [03](03_MODULE_SPECS.md#m09--revenue-pipeline-and-growth-intelligence) | T09-1/2/3 | V9-07 |
| M10 AI/run control | [03](03_MODULE_SPECS.md#m10--ai-provider-automation-control-audit-and-reliability) | T10-1/2/3 | V9-09 |

No development story may be marked complete until the test IDs are implemented and passing in CI/local + relevant staging checks.

## 4. Owner decisions that cannot be guessed

| Decision | Temporary development assumption | Needed before |
|---|---|---|
| Operating geography | Mumbai-first, no unverified pan-India SLA | Launching location SEO/ads/partner scheduling |
| First target customers | B2B offices; focus IT AMC + VC | Marketing copy and cold opportunity screening |
| Actual contact/address | V8 references are **unverified business facts** | Public launch and legal privacy notice |
| Partner capabilities/SLAs/rates | No automatic guarantee or margin assumption | Quotes, dispatch, service-area claims |
| Existing Hosting staging | **Not verified**; V8 source is not proof | Any public lead/portal release |
| Privacy policy/retention/processor terms | Draft, owner/legal review | Any live data intake or external AI processing |
| SMTP and sender | Unconfigured until tested | Real acknowledgement/follow-up sending |
| Google GBP approval | Unapproved; after 60-day eligibility still requires Google access | Live review display or authorized replies |
| Analytics/Search Console | Unknown property/link/consent status | Live conversion/SEO automatic reporting |
| Marketing budget | ₹0 default; Ads write disabled | Any paid campaign |
| AI runtime availability | OFF; deterministic guide works | Real AI assistant marketing claim |
| Quote/payment approach | Owner manually verifies external acceptance and collection | Binding quote/financial reporting |

Don't ask the owner for passwords or OAuth secrets inside GitHub issues, ChatGPT chat, screenshots or docs. Provide private config instructions instead.

## 5. Definition of done for every work item

- Affected design/API/schema documented **in the same PR**; tests added and executed, not only described.
- V8 original service pages, logo, partner permissions and approval rules continue to work.
- Private config placeholders only; production flags OFF; no leaked PII/keys/customer exports.
- Migration is additive/reviewed with staging backup and rollback analysis; no automatic destructive database script.
- Errors, external permissions, kill switch, idempotency and owner notification paths tested.
- GitHub PR points at `feature/v9-growth-engine` or a child feature branch; do not force-push `main`.
- `docs/PROJECT_STATUS.md` updated with verified SHA, tests, actual deployment status and still-open gaps.

## 6. Fresh agent handoff (must read before any coding)

1. Retrieve latest `mohdazizshaikh/aaditechsin.in` and check remote head, branch, working tree and authenticated account. The exact V8 starting SHA is `a551761be4846eba905a4461e7a416d1389c2ef1`; newer commits must be inspected, never assumed identical.
2. Read `README.md`, `README_DEPLOYMENT.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_STATUS.md`, `docs/SECURITY_CHECKLIST.md`, `docs/GAP_AUDIT_V8.md` plus all `docs/V9/*.md`.
3. Confirm host/staging access and owner-approved business facts; if missing, work locally with mocks and mark integration **BLOCKED**, not PASS.
4. Pick the next unblocked backlog ID. Make the smallest migration, API, UI, tests and docs update. No bulk app rewrite, no new VM unless justified.
5. Test V8 regressions, V9 module tests, git diff/secret scan, links and synthetic host flows. Report exact execution environment.
6. Commit to feature branch; verify remote SHA/file hashes; leave `main`/live Hostinger untouched until owner-approved PR/deployment. Record precise completed and pending items; never hallucinate integration success.

## 7. V9 document-only milestone checklist

- [x] Modular monolith and no AI dependency documented.
- [x] Ten module contracts, permission controls, external integrations and error fallbacks defined.
- [x] Proposed DB migrations, API envelopes, state machines and idempotency explained.
- [x] Privacy, explicit commercial approvals, licensing/consent and secrets boundaries captured.
- [x] Staging, backups, rollback, tests, dependencies, KPIs and owner decisions specified.
- [x] Implement initial V9 service-playbook/chat source (V9-02 subset), see [implementation report](10_V9_02_SERVICE_GUIDE_IMPLEMENTATION.md).
- [x] Implement V9-001 SQL and an independent, disabled V9 intake endpoint source with idempotency/consent tests; **not migrated/deployed or staging-accepted**, see [implementation report](11_V9_03_LEAD_FOUNDATION.md).
- [ ] Hostinger acceptance and customer production launch — **not authorized by this docs milestone**.
