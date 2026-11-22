# V9 module specifications — detailed but contained

**All M01–M10 are PLANNED**, not V8 production claims. Use the same PHP app, MySQL DB and owner dashboard. Each module below gives input, behavior, data/API boundary, controls, failure behavior, acceptance evidence and a success measure. Proposed API names are contracts, not existing routes.

## M01 — Acquisition, local SEO and service conversion

**Purpose:** attract real customers with useful, discoverable services; remove the need for separate manual enquiry handling for every page. **Pilot:** Mumbai IT AMC and Video Conferencing; next Data Recovery, Auditorium AV, Professional Sound, Hardware RFQ, Software.

- **Inputs:** owner-verified service scope/geography, genuine photos/case studies, contact details, approved CTA text, Search Console query/page metrics (once connected), and campaign UTMs.
- **Website features:** focused landing pages, verified service areas, FAQ schema only when appropriate and truthful, mobile click-to-call/email, prefilled service chat and simple qualification form. Maintain sitemap/canonical/robots metadata and original logo.
- **Processing:** record non-PII page/CTA analytics; pass a strict allowlisted service code and safe campaign attribution into M02/M03. Draft page improvements from observed search queries; owner reviews changes before publishing.
- **Interfaces:** proposed `GET /api/v9/service-config` returns public service codes + approved fields; `POST /api/v9/telemetry` accepts allowlisted non-PII events with sampling and rate limits. Public content remains server-rendered/static.
- **Storage:** existing pages plus service playbook configuration; `lead_attribution` stores sanitized UTM/referrer category on consented lead only. No invasive visitor fingerprinting.
- **Permissions:** only owner publishes copy and verifies locations, reviews and case studies. Never imply guaranteed first-page ranking or fake reviews.
- **Failures:** analytics off → page and form still work; Search Console connection off → scheduled review is manual; malformed UTM → drop safely.
- **Acceptance:** all service routes respond; mobile and keyboard navigation work; no broken CTAs, fabricated rating or private form data in analytics; new leads retain correct service/source; tests cover unknown UTM/source.
- **KPI:** organic qualified leads by service and conversion from service CTA → confirmed lead.

## M02 — AI-assisted chat and qualification

**Purpose:** answer approved FAQs in English/Hinglish, ask at most 3–5 relevant questions, help visitor choose the correct service and produce a usable scope summary without requiring AI for submission.

- **Inputs:** approved service playbooks (M01), visitor's explicit chat answers, timezone/general service area, optional preferred contact method, optional AI availability and consent state.
- **Outputs:** structured `service_code`, `answers`, `summary`, `urgency`, `next_action`, `needs_owner_review`, `confidence`, and public-safe answer; optional M03 lead on explicit contact consent.
- **Behavior:** service selection → purposeful follow-up → editable summary → contact consent → server-side lead save → accurate confirmation. Provide “talk to Aaditechs” fallback and disclose AI use only if real AI is connected.
- **AI adapter contract:** proposed `qualify(service_code, redacted_answers, approved_knowledge_version) -> JSON schema`; whitelist service codes, validate numeric/date ranges, strip dangerous markup, refuse sensitive secrets, treat retrieved content as data, not commands. Model cannot call arbitrary URLs, send messages or change jobs.
- **Integration:** `POST /api/v9/chat/qualify` accepts bounded question/answer payload; `POST /api/lead.php` remains authoritative for lead persistence until a deliberately versioned replacement is tested. Conversation text is ephemeral before submission.
- **Safety:** no passwords, file uploads or confidential device images; no “data recovery guaranteed”; no quoted price, stock, slot, engineering capacity or promised response time without owner-approved source. On urgent security/medical-like topics redirect to an appropriate human/help source rather than pretending to diagnose.
- **Failures:** model unavailable/slow/invalid output → deterministic V8 guided chat, never a blank form; DB failure → explain not submitted and show phone/email; duplicate submit → single lead reference via request idempotency.
- **Acceptance:** test seven service playbooks, unknown intent, English/Hinglish, wrong/contradictory answers, skip/modify, mobile keyboard, AI 5xx/timeout, prompt injection and explicit opt-out; verify no lead is stored before consent.
- **KPI:** qualified conversations per contact consent, customer correction/escalation rate, and lead quality (not total tokens/messages).

## M03 — CRM, triage, lead scoring and owner inbox

**Purpose:** centralize enquiries and prevent the solo owner from losing viable leads across the website, chat, phone/manual entry and referrals.

- **Input:** V8 `leads` insert/reference, M02 structured summary, source/UTM, manual owner note, verified serviceability and contact response, consent version.
- **Core behavior:** normalize fields → dedupe candidate by normalized contact/service/time window → create `lead_event` → place in owner inbox → label `new`, `qualified`, `responded`, `converted` or `closed` using existing V8 allowed statuses. Dedupe flags **for review**; do not auto-merge unrelated customers or erase submissions.
- **Qualification score:** simple explainable rule-based score, not black-box: service match, location serviced, reachable contact, project readiness, timeline. Sensitive personal attributes never influence scoring. Owner can override with a reason.
- **Views:** owner Inbox, Needs Info, Quote Requested, Follow-up Due, Won, Lost, Spam; filter service/lead age/source. “Lost reason” and follow-up date prevent silent abandonment.
- **Interfaces:** proposed owner-only `GET /api/v9/leads?status=...`, `PATCH /api/v9/leads/{id}`, `POST /api/v9/leads/{id}/events`, `POST /api/v9/leads/{id}/duplicate-review`. Existing API status updates remain authoritative until migrated.
- **Data:** `lead_events`, `tasks`, `lead_attribution` with immutable timestamps; current `leads` table remains master. Normalize phone/email for matching without publicly exposing hashes/PII.
- **Permissions:** owner only sees lead PII. Partners see approved *job scope* after assignment, no CRM search. Any bulk export requires authenticated owner and audit event; exclude raw lead export from public repo.
- **Failures:** SMTP outage does not lose lead; dedupe ambiguous → manual review; conflicting updates → optimistic version/409 conflict; retries never double-advance stage.
- **Acceptance:** duplicate case preserving both records; role denial; audit on score/status overrides; one lead reference after network retry; source attribution fallback “unknown”; row counts match accepted submissions.
- **KPI:** first-response time, number of unattended leads by age and owner-confirmed qualified leads.

## M04 — Follow-ups, appointment requests and messaging outbox

**Purpose:** timely response to consented enquiries and proposals without manual chasing, while keeping personal outreach polite and opt-out compliant.

- **Triggers:** new lead acknowledgement, owner due-date reminder, request for missing information, sent quotation check-in, stale qualified lead, job-status update, consented feedback request; no campaign triggered solely by discovered phone/email.
- **Input:** CRM stage, contact preference, purpose-specific consent, approved template version, owner timing policy, communication suppression list, per-lead time zone and idempotency key.
- **Behavior:** create a `task` and/or durable `message_outbox` record; cron picks due items; check consent and stage again immediately before sending. Approved transactional templates can be automatic after policy acceptance; sales/marketing sequence requires owner-approved audience + template + pause switch.
- **Appointment handling:** initially collect preferred date/time ranges as *requests*, not booked slots. Only confirm after owner/partner verifies actual calendar and capacity. No unapproved calendar integration.
- **Interfaces:** proposed `POST /api/v9/followups/schedule` owner only, `POST /api/v9/messaging/opt-out` authenticated or signed opt-out flow, `GET /api/v9/outbox` owner; worker calls provider adapter using secrets outside webroot.
- **Data:** `tasks`, `message_outbox` delivery state (`queued`, `sending`, `accepted`, `delivered`, `failed`, `suppressed`), consent and attempt log; no plaintext email credentials in event records.
- **Safety:** explicit STOP/unsubscribe handling for marketing, quiet hours and bounded cadence, hard bounce suppression, no marketing without lawful consent. TRAI/DLT rules apply to bulk commercial SMS/voice; WhatsApp/Meta templates and account permissions require separate setup and approval.
- **Failures:** network timeout → retry only if provider supports dedupe or outcome can be reconciled; missing consent → suppress; disabled SMTP → leave lead in inbox and mark notification pending; rollback must cancel unsent campaign tasks.
- **Acceptance:** consent revoked between queue and send → zero outbound; bounced address → no further attempts; simulated worker crash no silent duplicate where provider idempotency supported; repeat cron run safe; time zone/quiet hours respected; owner can pause all sequences immediately.
- **KPI:** share of leads responded to, due reminders completed, opt-outs/complaints and actual response rate.

## M05 — Quotations, partner orchestration and customer progress

**Purpose:** safely extend V8 owner + partner portal without a second marketplace application.

- **Existing authoritative workflow:** lead → owner qualifies → job `assessment` → `quote_pending` → `awaiting_customer_approval` → `approved` → `in_progress` → `work_completed` → `closed` or `cancelled`. V8 server rules are mandatory; no AI shortcut.
- **Inputs:** owner-verified assessment summary, partner specialty/actual service area, customer quote, estimated partner cost, cost assumptions, scope, availability confirmation, external customer approval evidence method/reference.
- **Behavior:** AI may generate **draft** scope/quote line suggestions tagged “estimate”; owner sets final prices, partner quote/cost, authorization; owner records customer approval evidence; partner may start only after `approved`. Updates are audit-logged. Customer tracking continues with minimal fields, expiring/rotatable link.
- **Interfaces:** reuse `/api/operations.php`, `/api/workflow.php`, `/portal/`, `/track/` until versioned refactor. Proposed V9 `POST /api/v9/jobs/{id}/draft-scope` is owner-only and **draft-only**; no direct publish/approval endpoint for model.
- **Data:** current `jobs`, `partners`, `job_activity`; proposed `job_expenses` for *actual* expenses/settlement. Do not overwrite historical quote/partner cost when a price revision occurs; record reason and reopening event.
- **Controls:** partner only assigned scope, no customer phone/email or owner margin by default. Before assign/reassign, owner acknowledges authorized scope and data sharing. Assign only active partners with confirmed relevant skills and capacity.
- **Failures:** partner unavailable → owner reassign after reviewing scope, no silent alternative; customer price rejection → return to quote stage and audit; tracking link expired → reissue privately; partner disabled → sessions revoked per V8.
- **Acceptance:** partner can't start without owner-recorded customer approval, can't view other jobs, can't adjust financials, can't override cancelled/closed; price change reopens approval; expired tracking token reveals nothing; estimated vs actual costs remain separate.
- **KPI:** quote-to-approved-job ratio, average turnaround, late jobs, direct job gross margin by partner/service.

## M06 — Opportunity discovery and B2B watchlist

**Purpose:** surface genuinely relevant public RFQs, tenders, partner referrals and owner-entered prospects for review; **not** send unsolicited spam.

- **Allowed sources:** owner-entered leads, sites explicitly permitting access, opted-in directories, public tenders/RFQs and lawful referrals. Each source must record link, timestamp, terms/usage basis, category, expiry and contact permission basis.
- **Behavior:** periodic bounded fetch/manual import → URL/content dedupe → extract service/location/deadline → compare to currently verified coverage/capabilities → score/recommend → create `opportunities` entry in private watchlist. **No automated email/call/WhatsApp outreach** by default.
- **Interface:** proposed owner-only `GET/POST /api/v9/opportunities`, `POST /api/v9/opportunities/{id}/review`; scheduler only accesses allowlisted sources. Avoid Google Maps/LinkedIn scraping, CAPTCHAs bypass or forged identities.
- **Data:** source URL, lawful retrieval metadata, public opportunity summary, service/deadline, confidence, review status (`new`, `reviewed`, `pursue`, `discarded`, `expired`), no private contact database harvested from third parties.
- **Owner actions:** pursue tender manually, prepare tailored proposal draft, request partner availability, reject, record outcome. Any outbound contact follows channel rules and manual approval.
- **Failures:** paywall/login/robots restriction → stop; stale deadline → expire; duplicate → link to existing item; unverified AI extraction → human-review badge.
- **Acceptance:** only allowlisted sources fetched, duplicate URLs collapse, no outreach sent, stale opportunities expire, provenance always visible and false positives don't become CRM contacts.
- **KPI:** relevant opportunities approved for pursuit and winning tender opportunities, not total scraped contacts.

## M07 — Content, social media and review-support desk

**Purpose:** turn real expertise into low-cost marketing that improves trust and search visibility.

- **Input:** approved services, genuine before/after project facts with permission, public FAQs, verified photos, Search Console insights, seasonal topics and owner's branding guide.
- **Behavior:** AI drafts SEO page updates, how-to articles, case-study summaries, LinkedIn/Google Business updates, caption and content calendar → owner fact-checks and approves → publish manually initially; approved API integration can later publish after channel-specific permissions.
- **Review handling:** draft owner-approved replies only to **real** verified reviews when access exists. Never generate fake customers, ratings, review screenshots, false certifications or scraped competitor reviews.
- **Interface:** proposed owner-only `GET/POST /api/v9/content`, `POST /api/v9/content/{id}/approve`; scheduling uses content status (`idea`, `draft`, `needs_review`, `approved`, `published`, `rejected`) and actual provider receipt. No direct model-to-social posting.
- **Data:** `content_drafts` fields topic, audience, service code, source facts, channel, version, schedule, approver, provider reference, outcome and citations/licenses for media.
- **SEO safeguard:** avoid mass-generated thin location pages or repeated AI copy aimed only at ranking; unique utility, genuine proof and manual editorial review required.
- **Failures:** account unauthorized → keep `approved` draft pending; failed publish → alert owner, never mark published; unsupported facts → reject and remove claims.
- **Acceptance:** every public claim traceable to owner-approved facts; published flag requires actual success; old V8 service pages remain reachable; approval and content history survive retry.
- **KPI:** useful search clicks, CTA conversions and attributable qualified leads; social likes are secondary.

## M08 — Paid campaign assistant and spend control

**Purpose:** make ads optional, measurable and owner controlled when cash flow justifies them; never assume a starting ad budget.

- **Input:** authorized ad account, explicit geographic coverage, approved service landing page, audience exclusion rules, monthly/daily spend cap, owner signoff, lead conversion data, creative claims and campaign objectives.
- **Behavior:** draft keyword themes, negative keywords, ad text and target landing pages; show conservative estimated funnel assumptions and tracked conversions; after explicit owner approval, optionally propose controlled API execution. Early V9 should be **recommendation only**.
- **Interface:** proposed owner-only `GET/POST /api/v9/campaigns`, `POST /api/v9/campaigns/{id}/approve-budget`, read-only metrics import. Any external mutating Ads API call must be separate allowlisted step with request fingerprint, approval ID and max spend cap enforced server-side.
- **Data:** `campaigns`: platform, account, approved budget/currency/cap, campaign draft, landing page, start/end, status, actual spend by provider, lead attribution and owner approval audit.
- **Safety:** billing must not be created/changed by AI; never increase cap automatically, target unserviceable regions, fabricate results, or run campaigns lacking a privacy notice/tracking compliance. Platform API eligibility/permissions are separate blockers.
- **Failures:** budget exhausted → pause suggestion; provider unavailable → no unlogged retry spend; missing owner approval → no action; poor quality leads → recommend halt but owner controls account.
- **Acceptance:** no spend-triggering action with approval absent/expired; cap unchanged under retries; provider/reporting mismatch flagged; test budget decimal precision and currency, region mismatch and cancelled campaign.
- **KPI:** cost per owner-verified qualified lead, cost per converted job, and contribution margin after marketing cost.

## M09 — Revenue, pipeline and growth intelligence

**Purpose:** show owner where the business actually makes money and which leads deserve attention.

- **Inputs:** V8 quote/cost, actual invoiced net amount, collected payments, refunds, actual partner and direct costs, campaign spend, lead source and closed job state. All financial updates require owner validation.
- **Processing:** separate `pipeline_estimate`, `approved_quote`, `invoiced`, `collected` and `realised_margin`; compute by service, partner and source with explicit period/currency and missing-data flags. Use deterministic SQL for money, not LLM arithmetic.
- **Interfaces:** proposed owner-only `GET /api/v9/reports/overview?from=&to=`, `POST /api/v9/jobs/{id}/expenses`, `POST /api/v9/jobs/{id}/collections`; no public or partner financial endpoints.
- **Data:** `job_expenses` and later `collections` ledger with amount, direction, type, tax treatment, related job, external reference and timestamp. No claim of gateway confirmation unless integrated and reconciled.
- **Dashboard:** actionable widgets: uncontacted leads, quotes awaiting action, jobs overdue, estimated vs realised margin, cost/qualified lead and source quality. Weekly email digest can be generated from database, no private customer details in public logs.
- **Failures:** incomplete costs → mark margin “partial/unverified”; refunds after close → adjust net period and preserve audit; duplicate transaction ref → reject double count; exchange currency → do not silently convert.
- **Acceptance:** test collected/unpaid/partially paid/refunded work, partner cost corrections, negative margin, tax exclusions, data access controls and ledger reconciliation; export doesn't contain secret tokens.
- **KPI:** verified monthly realised gross profit, revenue per owner hour, lead-to-close time and contribution by service.

## M10 — AI provider, automation control, audit and reliability

**Purpose:** safely automate repetitive work without creating a self-authorizing agent.

- **Input:** limited approved knowledge base, explicitly consented/necessary lead context, allowlisted task type, service playbook, provider endpoint/credentials in private config and tenant-specific timeout/budget settings.
- **Allowed AI tasks:** classify enquiry, extract structured fields, draft explanation/reply, suggest missing questions, prepare content, summarize *redacted* case notes and generate growth observations. No SQL, arbitrary web crawling, shell, customer/partner credentials, direct ad changes, payments or price/approval actions.
- **Guard:** PHP validator enforces JSON schema, max response length, approved vocabulary, privacy redaction, human decision state; store model version/prompt template revision/request ID and non-PII diagnostics, not customer text by default.
- **Fallback:** `ai_enabled=false` by default; request timeout → deterministic guide; transient 429/5xx → backoff/circuit breaker; excessive tokens → stop and route to owner; attacker-supplied prompt instructions always treated as untrusted input.
- **Admin control:** central controls for AI, outbound follow-ups, opportunity collector, content publishing and ads; each kill switch server-enforced. Owner can inspect scheduler backlog, provider errors, consent suppression, delivery failures and rollback actions.
- **Security:** localhost-only Ollama unless network access explicitly secured with TLS, auth and IP restrictions; do not expose model port publicly. Consumer chat goes via PHP backend, never direct browser-to-model credentials. Respect model licensing and provider data-processing terms.
- **Acceptance:** prompt injection cannot trigger writes/spend/partner dispatch; invalid model JSON cannot create a lead; secret redaction tests; 3-second/timeout fallback configurable; module OFF yields no outbound requests; logging has no tokens/PII; audit records owner gate.
- **KPI:** automation completion without human rework, safe fallback rate, cost per qualified interaction and weekly hours saved **verified by owner**, not model claims.

## Cross-module release dependency matrix

| First usable slice | Required modules | Enables | Still blocked |
|---|---|---|---|
| Slice A: real enquiries | V8 staging + M01/M02 basic guide + M03 | Lead capture & owner inbox | AI, marketing, partner auto-dispatch |
| Slice B: timely response | Slice A + M04 | Consented reminders | Unsolicited outreach, social auto-post |
| Slice C: profitable fulfilment | Slice B + M05/M09 baseline | Quote/job/margin visibility | Automatic approval, payment |
| Slice D: acquisition growth | Slice A + M07, optional M06 | Quality content/opportunity review | Unapproved external API publishing |
| Slice E: controlled experiments | Slices C/D + M08/M10 | Read-only/approved campaign analysis and AI drafting | Autonomous budget mutation |

**Important:** owner approval and external platform verification may keep any module disabled indefinitely without blocking basic website enquiry capture.
