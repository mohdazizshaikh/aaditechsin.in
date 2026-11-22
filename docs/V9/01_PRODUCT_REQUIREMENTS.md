# V9 product requirements — small-business growth engine

**Status:** SPEC ONLY. See [00_START_HERE](00_START_HERE.md) for factual V8 status.

## 1. Business model and outcomes

Aaditechs is a **coordinated service partner network**. The owner owns customer relationships, quotations, scope, supplier/partner selection and quality control; independent partners perform eligible work. Neither the chatbot nor website may imply Aaditechs employs every specialist directly. No inventory or certified specialist skill should be fabricated.

**Primary outcome:** more **qualified, profitable, serviced enquiries**, not inflated chatbot sessions, unqualified phone numbers or vanity traffic.

North-star reporting: `realised_gross_profit = customer_collections_excluding_taxes_and_refunds - actual_partner_and_direct_job_costs` for closed and reconciled work. Until payments/costs are manually reconciled, report *estimated* margin separately and never label it earned revenue.

### Audience and pilot geography

- Pilot default: Mumbai/Andheri and locations partners have confirmed they can service. Do not promise nationwide coverage absent coverage and response-time evidence.
- B2B first: office administrators, IT managers, facilities teams, procurement and SME owners; Data Recovery also supports private individuals with extra privacy care.
- Language: clear English and Hinglish; all key consent/price messages should be understandable and accessible on mobile.

### Service playbooks

| Code | Service | Questions required for qualification | Forbidden assistant commitments | Conversion CTA |
|---|---|---|---|---|
| `IT_AMC` | Office IT AMC/support | Location, device/user count, issues, coverage hours, start window, contact | Fixed SLA/engineer availability without check | Request AMC assessment |
| `VC` | Video conferencing | Room capacity/dimensions, Teams/Zoom needs, displays/audio, location, desired date, indicative range | Compatible hardware/room design certainty without survey | Plan conference room |
| `AUD_AV` | Auditorium AV | Venue size/seats, speech/music use, acoustics, current equipment, location, timeline | Acoustic guarantees or itemized prices without partner study | Request AV survey |
| `SOUND` | Professional sound/PA | Indoor/outdoor, audience, microphones/zones, venue, timeframe | Regulatory permits or noise outcome guarantees | Discuss sound system |
| `RECOVERY` | Data recovery | Device type, symptoms, stop-using advice, general location, urgency | Recovery guarantee, success percentage, price, collection of private file contents | Request safe assessment |
| `HARDWARE` | Hardware sourcing | Category/specs, quantity, brand preferences, location, target delivery | Stock, warranty, pricing, procurement commitment without confirmation | Request bulk RFQ |
| `SOFTWARE` | Software/automation | Business problem, user count, existing systems, integration requirements, delivery horizon | Unapproved scope, timeline or proprietary integration access | Discuss software project |

Keep sensitive device files, credentials, personal identification, passwords and confidential internal network details out of public chat. For unknown service cases use a general enquiry with human follow-up.

## 2. Personas and permissions

1. **Visitor:** read public pages, run guided assessment, optionally submit *contact consent*; cannot see another customer record.
2. **Qualified lead / customer:** receives an acknowledgement/reference and approved follow-ups; current V8 `/track/` only shows generic job status through private expiring token (not a customer account).
3. **Owner:** sees all leads, safe conversation summaries, marketing drafts, assignments, estimated margins and tasks; approves commercial actions.
4. **Partner:** sees only assigned job scope and permitted notes, never owner profit, unassigned leads or contact data by default.
5. **Scheduler:** a server cron process with minimal privileges; no separate human superadmin and no permission to approve quotes or spending.
6. **AI assistant:** a *non-human* suggestion/service principal subject to explicit allowlisted tools, maximum data scope and workflow guardrails.

## 3. End-to-end journeys

### A. Organic inbound to paid work

Search/Google Business Profile/referral → relevant service page → guide/CTA → consented lead saved + unique reference → categorize + deduplicate → owner queue and email notification → questions answered or owner assessment → partner survey when owner authorizes scope → owner quote review → customer approval **recorded with evidence** → approved work → partner updates → closure → actual costs/collection recorded → consented feedback/review request.

Never equate HTTP 200 from SMTP with delivered email; MySQL remains the source of truth. Contact form must show success only on confirmed DB save.

### B. Scheduled follow-up

Lead with explicit purpose-specific opt-in → queue a reminder/draft → owner or approved template sends message → record provider response/bounce → suppress on opt-out, conversion, duplicate, complaint, hard bounce or owner pause. Use no SMS/cold WhatsApp automation by default.

### C. Opportunity discovery

Permitted public tenders/RFQs, first-party enquiries and owner-entered sources → capture source URL, date and permission basis → relevance score + manual review → owner decides whether to submit/participate. No automated scraping against platform restrictions, fake identities or unsolicited bulk messages.

### D. Marketing-to-sale attribution

Content/campaign draft → owner validation → publish via approved integration → visitor lands with valid attribution values → lead captured → opportunity/quotation linked → costs and collections recorded → monthly ROI report. Link campaigns to actual customers only after valid consent and attribution evidence; avoid claiming perfect multi-touch attribution.

## 4. Conversion experiences

- Each service page: problem addressed, real coverage, process, genuine proof/case study (when approved), FAQs, a clear CTA, accessible contact fallback and privacy notice.
- The chat must be concise (typically 3–5 qualification steps), support corrections/skip, allow instant contact or escalation, never force a budget question, and not persist before consent.
- Chat displays “AI-assisted” only when a real live model is connected and tested; otherwise clearly states “guided assessment”. An assistant response must never impersonate a human.
- Lead acknowledgement carries a reference and next-step expectations *without unsupported guaranteed response time*.
- Trust: verified business details, real photos/case studies and no paid/fabricated customer reviews.

## 5. Success measurement and definitions

| Metric | Definition | Source | Guardrail |
|---|---|---|---|
| Qualified leads | Owner-confirmed requirement + reachable contact + eligible location + service need | CRM | Never score contact as qualified solely because of chatbot sentiment |
| Conversion | `converted leads / eligible unique leads` within defined period | CRM | Deduplicate; exclude tests/spam |
| First response | Time from DB-confirmed lead creation to first real customer-facing response | CRM/outbox | Queue creation alone is not response |
| Proposal turnaround | Time from qualified lead to owner-approved proposal delivery | CRM | Draft ≠ delivered |
| Booking rate | Approved scoped jobs / quotes sent | Jobs | Track lost/withdrawn reasons |
| Estimated margin | Approved customer quote minus estimated partner and direct costs | Jobs | Label *estimate* |
| Realised gross profit | Collected net revenue minus actual direct costs on reconciled jobs | Finance ledger | Track refund/tax and payment state; do not assume payment |
| Cost/qualified lead | Paid campaign spend / verified qualified leads attributed to campaign | Campaign ledger | Spend requires owner signoff |

Track at least `page_view`, `service_cta_click`, `chat_started`, `qualification_completed`, `generate_lead`, `quote_sent`, `job_approved` and `job_closed` with no PII in analytics. Production GA4/Search Console integration is pending; GA4 `generate_lead` is a recommended event.

## 6. Hard non-functional requirements

- Mobile-first; keyboard accessible; progressive enhancement, chat fallback, readable Hindi/English prompts; HTML forms functional if AI is down.
- Enquiry capture should fail safely on DB outage (no false success); provide alternative phone/email and incident alert. Never write raw lead data into public browser storage.
- Idempotent duplicate-safe API and retries for all actions causing side effects. System clock UTC; store business display timezone `Asia/Kolkata`.
- Database backups, retention, minimal PII, least privilege, CSRF/session controls and row scoping from V8 remain mandatory.
- Hostinger shared-host resource limits: short cron batches, bounded outbound HTTP timeouts, no always-on queue worker or in-hosted 30B inference.
- Feature flags and *kill switches* for AI, follow-ups, publishing, discovery and ads, all OFF by default.

## 7. Out-of-scope unless separately approved

Autonomous ad spend, unsupervised bulk outreach, automatic binding contracts or discounted quotes, partner payments, custom ERP, direct confidential data-recovery upload, voice calling, impersonation, or an unreviewed payment gateway. No promise of guaranteed lead volume or search ranking. Nationwide service expansion requires partner coverage verification.
