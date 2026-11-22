# Aaditechs website — handoff status

Baseline: V6 Hostinger deployment kit, created 18 November 2026; imported to this repository on 23 November 2026.

## Existing / delivered source

- Original Aaditechs orange-blue brand logo and modern HTML5 website concept.
- Data Recovery service and general IT service content.
- Guided chat UI (keyword-based; **not** real AI chat or a staff messaging service).
- Browser-side integration code and off-by-default feature switches.
- PHP endpoints: Google Business Profile review fetcher and SMTP lead email handler.
- Hostinger protected config template, composer dependencies declaration, and deployment runbook.

## External services pending

- Google Business Profile API application rejected at eligibility stage because the business profile had not yet been verified for 60 days. Recheck eligibility on or after 5 December 2026; **approval is not automatic**.
- Google OAuth redirect setup, final authorization and refresh token: not configured.
- SMTP credentials, message delivery tests and privacy/anti-spam checks: pending.
- Hostinger staging and live deployment: not performed; no published production changes are implied.

## Feature backlog (do not confuse with implemented source)

1. Audio Visual division: video conferencing, auditorium integration, professional PA/sound systems and AV support.
2. AV project requirement builder and partner quote/approval workflow.
3. Smart chatbot with human handoff and consent-based enquiries.
4. Solo-operator lead dashboard, partner operations and customer case tracking.
5. Google Business Profile analytics/review integration after API access and compliance review.

## Development discipline

- Work on feature branches; do not modify the original brand logo.
- Do not reintroduce 'Zenith Soles' or 'Robotics Lab' as group companies.
- Protect all secrets and customer data outside Git and the webroot.
- Before production: verify staging, SMTP, PHP dependencies, exact paths, access controls, SSL and recovery/rollback.
- `README_DEPLOYMENT.md` is the operational setup guide; update this file when implementation statuses change.
