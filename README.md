# Aaditechs — Website, Partner Operations & Growth Platform

**Source repository** for Aaditechs' modern website, Audio Visual (AV) solutions, guided assessment chatbot, consent-based Lead Engine, and a private owner/partner work-order portal.

Production: `https://aaditechs.in/` — **not deployed by this source commit**. Previous hosted *static* concept preview: `https://aaditechs-modern-concept-2026.tiledparrot.chatgpt.site` — may still show an earlier V6 until explicitly updated. No CRM data, credentials or production customer records are included in Git.

## Growth development status (24 November 2026)

Main V9.6 source verified; V9.7–V10.6L development is stacked and not deployed. See [current project tracker](docs/PROJECT_STATUS.md), [SEO guide](docs/V100_SEO.md), [Meta text publishing](docs/V101_META.md), [Other social networks](docs/V102_NETWORKS.md), [Private media uploads](docs/V102A_MEDIA_UPLOAD.md), [Instagram Reels](docs/V102B_INSTAGRAM.md), [Facebook media](docs/V102C_FACEBOOK_MEDIA.md), [LinkedIn/X images](docs/V102D_NETWORK_MEDIA.md), [Meta images](docs/V102E_META_IMAGES.md), [Private media inventory](docs/V102F_MEDIA_STORAGE.md), [OAuth lifecycle](docs/V102G_OAUTH_HEALTH.md), [Media recovery](docs/V102H_MEDIA_RECOVERY.md), [LinkedIn video batch](docs/V102H1_VIDEO_BATCH.md), [X/Threads video batch](docs/V102H2_VIDEO_BATCH.md), [Carousel and private inbox batch](docs/V102H3_CAROUSEL_TRANSFER.md), [staging gates](docs/STAGING_ACCEPTANCE.md) and [GitHub issue #2](https://github.com/mohdazizshaikh/aaditechsin.in/issues/2). Public guide, private model workflows and integrations are independently gated OFF. Source implementation is not production acceptance.

## Existing website and operations

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

V10.2I/J source batch: [private SFTP transfer and integration health](docs/V102IJ_TRANSFER_HEALTH.md). CI pending; no live acceptance.

V10.2K/V10.3 source batch: [Telegram media and communications aggregates](docs/V102K_V103_COMMUNICATIONS.md). CI pending; no live acceptance.

V10.3A/V10.4 source batch: [Gmail metadata, authorized discovery batch and opportunity reminders](docs/V103A_V104_INTELLIGENCE.md). CI pending; no live acceptance.

V10.5 source batch: [private owner intelligence dashboard](docs/V105_OWNER_DASHBOARD.md). CI pending; no live acceptance.

V10.5A/V10.6A source batch: [advanced aggregate reporting and release readiness](docs/V105A_V106A_RELEASE_READINESS.md). CI pending; no live acceptance.

V10.6B source batch: [Gmail renewal candidates and communications retention](docs/V106B_CREDENTIAL_RETENTION.md). CI pending; no live acceptance.

V10.6C source batch: [signed communication bridges and consolidated release gates](docs/V106C_COMMUNICATIONS_RELEASE.md). CI pending; no live acceptance.

V10.6D source batch: [publication review, backup verification and credential pre-activation checks](docs/V106D_RECONCILIATION_BACKUP.md). CI pending; no live acceptance.

V10.6E source batch: [native provider usage and synthetic restore rehearsal](docs/V106E_NATIVE_USAGE_RESTORE.md). CI pending; no live acceptance.

V10.6F source batch: [Telegram albums and authenticated WhatsApp inbound events](docs/V106F_ALBUMS_INBOUND.md). CI pending; no live acceptance.

V10.6G source batch: [Twilio inbound callbacks and controlled WhatsApp retention](docs/V106G_INBOUND_RETENTION.md). CI pending; no live acceptance.

V10.6H source batch: [mixed Meta carousels and internal communication reviews](docs/V106H_MIXED_REVIEWS.md). CI pending; no live acceptance.

V10.6I source batch: [Search Console finalized search metrics and ephemeral OAuth refresh](docs/V106I_SEARCH_CONSOLE.md). CI pending; no live acceptance.

V10.6J source batch: [historical Search Console and bounded public SEO observation](docs/V106J_SEO_HISTORY_CRAWL.md). CI pending; no live acceptance.

V10.6K source batch: [local AI runtime readiness, SEO history retention and GBP media read checks](docs/V106K_RUNTIME_RETENTION.md). CI pending; no live acceptance.

V10.6L source batch: [pause-safe discovery, cadence and reviewed recovery](docs/V106L_DISCOVERY_AUTOMATION.md). CI pending; no live acceptance.
