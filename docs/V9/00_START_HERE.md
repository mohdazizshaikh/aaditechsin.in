# Aaditechs V9 — start here

**Branch:** `feature/v9-growth-engine` (created from verified V8 `main` at `a551761be4846eba905a4461e7a416d1389c2ef1`).  
**Status:** product/design specification; **V9 capabilities are NOT implemented, enabled, or deployed.**  
**Last planning review:** 23 November 2026.  
**Owner:** Aaditechs (solo owner coordinating independent service partners).

## One-sentence goal

Turn the existing Aaditechs website and V8 lead/partner portal into a simple, consent-based growth and sales system that attracts qualified enquiries, handles routine conversations and follow-ups, coordinates partners, and measures **realised gross profit**, while leaving commitments and spending with the owner.

## Keep it simple: five rules

1. **One application, one database:** extend the current PHP + MySQL/MariaDB site. No WordPress migration, microservice fleet, Kubernetes, or required second VM.
2. **Automate the repeatable:** forms, qualification, task reminders, draft replies, deduplication, reporting, and consented follow-ups; final prices, scope, campaign spending, legal notices, and partner dispatch require owner approval.
3. **AI is optional:** deterministic forms and CRM work when the model is offline. Ollama/OpenAI-compatible inference is an optional private adapter, never a public PHP request to a model or a mandatory 30B runtime on shared hosting.
4. **Ship one vertical at a time:** launch Mumbai-first IT AMC + Video Conferencing qualification pilot; reuse playbooks later for Data Recovery, AV/Auditorium, Hardware, and Software. Geography beyond service capacity is a business decision, not an automatic promise.
5. **No fake activity:** do not claim paid jobs, guaranteed leads, verified reviews, a live human or AI agent, deployment, provider approval, or delivery capability without evidence.

## What exists today (V8 source, not V9)

- Website, original logo, service pages, AV planner and English/Hinglish **rule-based** guide.
- Consent-based PHP lead endpoint, MySQL schema, owner lead list, partner assignments, owner-recorded quote approval, restricted customer tracking, and security hardening.
- All live lead/portal/Google integration flags default OFF; real Hostinger staging and production acceptance remain **pending**.
- Google Business Profile API access remains unapproved. The profile went live around 4–5 October 2026; eligibility may be rechecked around 5 December, but is not an approval.

## V9 document map — read in this order

1. [01_PRODUCT_REQUIREMENTS.md](01_PRODUCT_REQUIREMENTS.md) — customers, services, goals, journeys, business decisions, KPIs.
2. [02_ARCHITECTURE.md](02_ARCHITECTURE.md) — runtime, ownership, modules, events, operational safeguards.
3. [03_MODULE_SPECS.md](03_MODULE_SPECS.md) — ten fully scoped modules and acceptance rules.
4. [04_DATA_AND_API_CONTRACTS.md](04_DATA_AND_API_CONTRACTS.md) — proposed migrations, payloads, permissions and state invariants.
5. [05_SECURITY_PRIVACY_APPROVALS.md](05_SECURITY_PRIVACY_APPROVALS.md) — trust boundaries, human approval, consent and abuse control.
6. [06_DEPLOYMENT_AND_ROLLBACK.md](06_DEPLOYMENT_AND_ROLLBACK.md) — Hostinger, private secrets, staging, progressive release and rollback.
7. [07_TEST_AND_ACCEPTANCE.md](07_TEST_AND_ACCEPTANCE.md) — test matrix, edge cases and release evidence.
8. [08_BACKLOG_AND_DECISIONS.md](08_BACKLOG_AND_DECISIONS.md) — small deliverables, dependencies, decisions and explicit handoff.

## Operational truth table

| Capability | V8 baseline | V9 target | May be advertised as live now? |
|---|---|---|---|
| Public service pages | Source implemented | Improve conversion/SEO | Only after actual public verification |
| Guided chat | Deterministic demo | AI-assisted qualifying + human escalation | **No** AI-live claim |
| Lead persistence | Source implemented, disabled | CRM enrichment, dedupe, events | **No**, until staging+enablement |
| Partner cases/quotes | Source implemented, disabled | Approved quote workflows + margin reporting | **No**, until staging+enablement |
| Email follow-ups | Optional existing one-time notification | Consented sequence with suppression | **No** |
| SEO/content | Static pages/sitemap | Search Console-driven drafts | **No** autonomous publishing claim |
| Lead discovery | Not implemented | Permission-respecting watchlist | **No** |
| Marketing/ads | Not implemented | Drafts, measurement, approved spending only | **No** |
| Profit reporting | Existing quote/cost fields | Actual cost/revenue reconciliation | **No** |
| AI runtime | Not connected | Secured optional provider adapter | **No** |

## Definition of done for V9

Implementation is **not** complete merely when Markdown is merged. Each module must have the API and data model implemented, tests passing, staging evidence, owner-approved privacy/terms and business facts, rollback readiness, cost controls and truthful public UX. A module may be called *production live* only after its separately documented release gate is signed.

## Non-negotiables

- Preserve the original orange/blue logo, services and truthful coordinated-partner positioning. Do not restore Zenith Soles or Robotics Lab.
- Public repo `mohdazizshaikh/aaditechsin.in`: **never commit secrets, raw leads, live exports, private customer information, partner passwords, or OAuth tokens**.
- Private config stays outside `public_html`; staged migrations/feature flags remain OFF until verified.
- Do not overwrite `aaditechs.in` without a verified recoverable backup and explicit owner cutover approval.
- The owner is responsible for quote approval, partner work authorization, all ad spend and any outbound marketing launch.
