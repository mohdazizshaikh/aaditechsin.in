# Aaditechs — Website, Partner Operations & Growth Platform

**Source repository** for Aaditechs' modern website, Audio Visual (AV) solutions, guided assessment chatbot, consent-based Lead Engine, and a private owner/partner work-order portal.

Production: `https://aaditechs.in/` — **not deployed by this source commit**. Previous hosted *static* concept preview: `https://aaditechs-modern-concept-2026.tiledparrot.chatgpt.site` — may still show an earlier V6 until explicitly updated. No CRM data, credentials or production customer records are included in Git.

## Growth development status (24 November 2026)

Main V9.6 source verified; V9.7–V10.6W development is stacked and not deployed. See [current project tracker](docs/PROJECT_STATUS.md), [SEO guide](docs/V100_SEO.md), [Meta text publishing](docs/V101_META.md), [Other social networks](docs/V102_NETWORKS.md), [Private media uploads](docs/V102A_MEDIA_UPLOAD.md), [Instagram Reels](docs/V102B_INSTAGRAM.md), [Facebook media](docs/V102C_FACEBOOK_MEDIA.md), [LinkedIn/X images](docs/V102D_NETWORK_MEDIA.md), [Meta images](docs/V102E_META_IMAGES.md), [Private media inventory](docs/V102F_MEDIA_STORAGE.md), [OAuth lifecycle](docs/V102G_OAUTH_HEALTH.md), [Media recovery](docs/V102H_MEDIA_RECOVERY.md), [LinkedIn video batch](docs/V102H1_VIDEO_BATCH.md), [X/Threads video batch](docs/V102H2_VIDEO_BATCH.md), [Carousel and private inbox batch](docs/V102H3_CAROUSEL_TRANSFER.md), [staging gates](docs/STAGING_ACCEPTANCE.md) and [GitHub issue #2](https://github.com/mohdazizshaikh/aaditechsin.in/issues/2). Public guide, private model workflows and integrations are independently gated OFF. Source implementation is not production acceptance.

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

V10.6M source batch: [bulk creative coverage, replay-preserving Twilio retention and release validation](docs/V106M_BULK_COVERAGE_RELEASE.md). CI pending; no live acceptance.

V10.6N: [bounded creative batch and draft integrity](docs/V106N_BOUNDED_CREATIVE_REVIEW.md). New CI pending; no live acceptance.

V10.6O: [discovery scheduler readiness and evidence integrity](docs/V106O_DISCOVERY_SCHEDULER_INTEGRITY.md). New CI pending; no live scheduling or acceptance.

V10.6P: [owner coverage intelligence](docs/V106P_OWNER_COVERAGE_INTELLIGENCE.md). New CI pending; no live acceptance.

V10.6Q: [bounded pinned local AI worker](docs/V106Q_PINNED_LOCAL_WORKER.md). New CI pending; no live inference.

V10.6R: [read-only schema and backup release gates](docs/V106R_SCHEMA_RESTORE_GATE.md). New CI pending; no actual hosted restore.

V10.6S: [reviewed local media batches](docs/V106S_REVIEWED_MEDIA_BATCHES.md). New CI pending; no live rendering or publication acceptance.

V10.6T: [reviewed private media transfer](docs/V106T_REVIEWED_MEDIA_TRANSFER.md). CI pending; no live transfer.

V10.6U: [reviewed server-side media import](docs/V106U_REVIEWED_SERVER_IMPORT.md). New batch flag OFF; CI pending.

V10.6V: [private media reference audit and retention planning](docs/V106V_MEDIA_INTEGRITY_AUDIT.md). New CI pending; no live file operations.

V10.6W: [reviewed OAuth health batch](docs/V106W_OAUTH_HEALTH_BATCH.md). New batch flag OFF; CI pending.


## V10.6X — reviewed opportunity follow-up batches (source only)
Private OFF-by-default 1–50 reminder set/reschedule/complete/cancel batches, read-only exact-state plan, digest-bound atomic apply, per-item audit, single-action audit rollback and 73 scope + 84 service regression cases. No external outreach or production changes. See [V10.6X](docs/V106X_REVIEWED_FOLLOWUP_BATCH.md) for operator gates; CI and actual staging pending.


## V10.6Y — reviewed communications and retention integrity (source only)
New default-OFF private 1–50 aggregate review batch across email, WhatsApp, SMS and calls; exact-state plan/apply, per-item audit, local rollback and full-state retention digest. 108 scope and 24 channel scenarios authored, plus retention drift tests. Prior V10.6X CI passed. New CI and actual hosting/consent/retention acceptance pending. See [V10.6Y](docs/V106Y_COMMUNICATION_REVIEW_BATCH.md).


## V10.6Z — private page-level SEO diagnostics (source only)
Optional detailed seven-page observation v2 with strict path/status/finding consistency, aggregate-only persistence, v1 compatibility, atomic Search Console snapshot/history/audit and live SEO audit rollback. 56 crawler + 126 report validation scenarios authored. Prior V10.6Y CI passed; new CI and actual crawl/provider/staging acceptance pending. See [V10.6Z](docs/V106Z_PAGE_SEO_DIAGNOSTICS.md).


## V10.7A — competitor editorial evidence (source only)
Offline private operator-supplied own/peer comparison across 14 services and six editorial signals (84 cells), 1–5 peers, explicit unknown evidence, source references and review-only gap suggestions, 504 synthetic decision cases. No scraping, actual competitor claims, rankings, publishing or database changes. Prior V10.6Z CI passed; new CI and real evidence acceptance pending. See [V10.7A](docs/V107A_COMPETITOR_EVIDENCE.md).


## V10.7B — reviewed editorial task batches (source only)
Private default-OFF competitor-to-SEO review handoff, 1–84 selected cells, exact reviewed plan, stale digest rejection, atomic audited pending-review task creation and existing-task preservation. 84 export + 168 scope cases and synthetic 84-task database test authored. No live competitor data, external publishing or production changes. See [V10.7B](docs/V107B_COMPETITOR_REVIEW_TASKS.md).


## V10.7C — restore row-count reconciliation (source only)
Private read-only count capture/compare across 49 contract tables; exact metadata/backup binding, canonical reference, table-specific match/drift, pause and worker gates, backup FIFO/size safeguards. 98 table-specific scenarios and disposable DB count checks authored. Prior V10.7B CI passed; new CI and actual isolated restore pending. See [V10.7C](docs/V107C_RESTORE_COUNT_RECONCILIATION.md).


## V10.7D — keyed restore content fingerprints (source only)
Private bounded read-only content fingerprint comparison across 49 tables; keyed per-table HMAC with primary-key order, binary field framing, metadata/backup binding and canonical reference. 98 table cases and same-count mutation regression authored. Prior V10.7C CI passed; new CI and actual isolated restore pending. See [V10.7D](docs/V107D_RESTORE_CONTENT_FINGERPRINTS.md).


## V10.7E — private media restore parity (source only)
Full bounded active/quarantine asset verification and keyed parity across 15 reference sources, missing-reference readiness gate, no raw asset names exported, three staging flag visibility updates. 51 manifest matrix cases authored. Prior V10.7D CI passed; actual restoration and new CI pending. See [V10.7E](docs/V107E_MEDIA_RESTORE_PARITY.md).


## V10.7F — staging configuration and schema preflight (source only)
113 explicit flag states, 76 child flag dependency checks, strict all-off isolated staging baseline, all 49 contract tables/required columns and 437 matrix cases authored. Prior V10.7E CI passed; new CI and actual hosted acceptance pending. See [V10.7F](docs/V107F_STAGING_PREFLIGHT.md).


## V10.7G — exact deployment integrity (source only)
Commit-derived bounded SHA-256 inventory of all tracked public files/private maintenance scripts, private staged-copy parity, missing/drift/extra/unsafe checks, preflight config path and permission gate, 102 synthetic file cases. Prior V10.7F CI passed; new CI and actual hosting pending. See [V10.7G](docs/V107G_DEPLOYMENT_INTEGRITY.md).


## V10.7H — strict JSON boundaries (source only)
Seven reviewed decode calls reject repeated decoded members; existing scope/authorization controls retained. 200 matrix cases and six integrated scope tests authored; development push CI trigger added. V10.7G PR/CI unconfirmed at initial check; new CI and actual hosting pending. See [V10.7H](docs/V107H_STRICT_JSON_BOUNDARIES.md).


## V10.7I — local native video review candidates (source only)
Offline fixed CogVideoX candidate adapter, reviewed 1–12 job/model/font/seed plan, 14-service/9-channel coverage, bounded private motion rendering and review-only index, no transfer or publication. 126 plan cases and synthetic moving-frame codec tests authored. Prior V10.7H CI passed; actual GPU/model, new CI and hosted acceptance pending. See [V10.7I](docs/V107I_NATIVE_VIDEO_CANDIDATES.md).


## V10.7J — reviewed OAuth rotation and native-video CI correction (source only)
Seven provider replacement credentials can be privately planned and identity-verified without activation. 84 provider cases authored; separate default-OFF flag, HMAC binding, pause/config gates and fail-stop. Corrects V10.7I font fixture without relaxing production checks. New CI pending; actual provider renewal, GPU inference and hosted acceptance pending. See [V10.7J](docs/V107J_OAUTH_ROTATION_REVIEW.md).


## V10.7K — business-facing website completion (source only)
Nine new dedicated service pages; catalog coverage 5/14 to 14/14, static homepage navigation, sitemap/analytics/live SEO coverage 16 public paths, first-load improvements and no-JS inquiry safety. Real performance measurement, content sign-off and deployment pending. See [V10.7K](docs/V107K_SERVICE_WEBSITE.md).
