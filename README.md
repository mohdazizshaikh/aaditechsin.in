# Aaditechs — Website, Partner Operations & V9 Growth Planning

> **Version clarity:** `main` is the V8 implemented-source baseline. New branch `feature/v9-growth-engine` was created from V8 commit `a551761be4846eba905a4461e7a416d1389c2ef1` for V9 **planning and future incremental development**. The V9 module documents do **not** mean any AI, ads, autonomous marketing, CRM upgrades or growth automation is currently built or live. Start at [V9 Start Here](docs/V9/00_START_HERE.md).

**Source repository** for Aaditechs' modern website, Audio Visual (AV) solutions, guided assessment chatbot, consent-based Lead Engine, and a private owner/partner work-order portal. V9 plans to extend it into a single-owner, partner-coordinated sales and growth engine without moving away from lightweight PHP + MySQL hosting.

Production: `https://aaditechs.in/` — **not deployed by this source commit**. Previous hosted *static* concept preview: `https://aaditechs-modern-concept-2026.tiledparrot.chatgpt.site` — may still show an earlier V6 until explicitly updated. No CRM data, credentials or production customer records are included in Git.

## What's implemented in V8 source

- Original Aaditechs logo retained; Data Recovery, IT Infrastructure, Software, Support, Cloud, Security and Procurement retained.
- **Audio Visual division:** Video Conferencing, Auditorium AV, Professional Sound & PA, AV Installation/Maintenance with homepage category filter.
- SEO-oriented service pages at `/video-conferencing-solutions/`, `/auditorium-av-solutions/`, `/professional-sound-systems/`, `/data-recovery/` and `/it-amc/`.
- `/av-project-planner/`: room type, capacity, components, site, project stage, timeline, optional budget and customer consent; preliminary scope preview, no fake automated price.
- **Smart Guide v2:** English/Hinglish keyword-based service routing, 3-step requirements discovery for five service classes, optional consent-based inquiry, accessible mobile chat. **Not a generative AI model or live agent.**
- **Lead Engine:** secure JSON endpoint `/api/lead.php`; validation, throttling, consent, MySQL storage and optional SMTP notification. Returns lead reference only after successful storage. Disabled by default.
- **Owner/partner portal:** `/portal/` PHP session login, role permissions, owner lead triage, partner creation/access controls, case creation/assignment, owner-only quotation/cost entries, manual approval evidence audit, restricted partner state transitions and partner session revocation. Disabled by default.
- **Customer case tracking:** `/track/` accepts unguessable one-time-generated link, displays only limited case information; token stored as SHA-256 hash. Disabled by default.
- Google Reviews API integration remains present but disabled until Google grants access; no invented reviews.
- Updated Hostinger secrets templates, SQL schema, SEO sitemap, security headers and deployment guide.

## Technical stack

Static HTML5/CSS/JavaScript (no frontend build dependency), PHP 8.1+ (8.2+ recommended), MySQL 8 / MariaDB 10.5+, PDO MySQL, OpenSSL, optional cURL (Google) and Composer/PHPMailer (SMTP). Designed for **Hostinger PHP shared hosting**, after confirming actual plan capabilities and filesystem layout.

```
public_html/
  index.html
  assets/                         styling, images, scripts, original logo
  video-conferencing-solutions/  AV landing page
  auditorium-av-solutions/       AV landing page
  professional-sound-systems/    AV landing page
  av-project-planner/            self-service scoping
  data-recovery/                 safety-first recovery landing page
  it-amc/                        office maintenance landing page
  privacy/                       DRAFT NOTICE: owner/legal review required
  api/{bootstrap,operations,lead,google-reviews}.php
  portal/index.php               private owner/partner operations
  track/index.php                limited token-gated customer status
  robots.txt  sitemap.xml
secure-setup/                    TEMPLATE ONLY, never upload under webroot
  aaditechs-config.php.example
  schema.sql
  composer.json
  migrations/2026-11-23-v8.sql   existing V7 staging DB upgrade (review before importing)
  maintenance/prune.php
README_DEPLOYMENT.md              Hostinger step-by-step runbook
PROJECT_STATUS.md                 verified implementation vs external blockers
```

**Secret location in production:** `<domain-root>/secure/aaditechs-config.php`, a sibling to `public_html`, never public. Configure environment-specific credentials there and keep `.gitignore` in place. `public_html/assets/site-config.js` contains **public feature flags only**. The real config must be created privately on the server.

## Deployment status

Source implementation and offline smoke tests are available. **Live SMTP, database persistence, user permissions, OAuth and Hostinger staging have not been tested against real infrastructure.** All production-sensitive feature flags start `false`. Do not turn them on until the checklist in [README_DEPLOYMENT.md](README_DEPLOYMENT.md) is complete.

## V9 development documentation — new branch, NOT running software

The clean-scope V9 plan is in [`docs/V9/00_START_HERE.md`](docs/V9/00_START_HERE.md), followed by product requirements, architecture, **ten detailed module specs**, proposed schema and API contracts, security/approvals, Hostinger deployment/rollback, tests, and a staged delivery backlog. Do not confuse a documented proposed endpoint (`/api/v9/`) with an implemented endpoint. Only approved V8 code can run until each V9 release slice is written, tested and enabled in staging.

## Verification

```bash
for f in public_html/assets/*.js; do node --check "$f"; done
find public_html secure-setup -name '*.php' -exec php -l {} \;
php tests/workflow_rules.php
python tests/site_integrity.py
python tests/http_smoke.py
python tests/security_static.py
python tests/browser_smoke.py  # requires Python playwright + local Chromium; uses in-memory page preview
```

See [docs/GAP_AUDIT_V8.md](docs/GAP_AUDIT_V8.md) and [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), and [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md). Keep new work scoped; do not reintroduce Zenith Soles / Robotics Lab, invent customer reviews, or change the original brand logo without owner approval.
