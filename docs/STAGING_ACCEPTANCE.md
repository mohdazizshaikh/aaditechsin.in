# Aaditechs V8+ — Hostinger staging acceptance (not yet deployed)

**Status at 23 Nov 2026:** The `main` source and all local/offline tests were verified; the Hostinger account was **not authenticated/accessible in the current assistant session**. Nothing was uploaded to Hostinger or configured in production. The `source-validation.yml` GitHub Action runs source checks only; green CI is **not** hosting acceptance.

## Staging prerequisites

1. Connect the Hostinger Connector to this ChatGPT/Codex environment using its official MCP integration (`https://mcp.hostinger.com`). Do not paste passwords or tokens into chat. Confirm the exact Hostinger **website and account** before any change. If the target staging site has existing content, obtain explicit owner approval and a verified backup before an overwrite.
2. Provision or identify a **separate, HTTPS staging domain/subdomain** for V8 tests. Restrict discovery by password/access controls and `X-Robots-Tag: noindex`; do not rely only on robots.txt. Do not change production DNS or overwrite `aaditechs.in`.
3. Verify PHP >= 8.1, `pdo_mysql`, `curl`, `openssl`, `json`, HTTPS redirect, private directory **outside** webroot, database support, SMTP capability, and backups. The reference file layout in `README_DEPLOYMENT.md` is illustrative until the actual Hostinger website path is confirmed.
4. Deploy only the contents of `public_html/` to staging webroot. Put `secure-setup/aaditechs-config.php.example` in staging's private `<domain-root>/secure/aaditechs-config.php`, replacing placeholders privately. Put `secure-setup/maintenance/preflight.php` in `<domain-root>/secure/maintenance/preflight.php`. Keep everything else in `secure-setup/`, docs and tests outside the webroot. Do not commit private config.
5. In staging private config, set `public_base_url` to the **actual HTTPS staging origin**; use test-only owner/partner credentials, SMTP test inbox and a dedicated staging database. **All feature flags OFF initially.**

## Read-only preflight on staging

From the hosting shell (or private CLI console), run:

```bash
php /path/to/domain-root/secure/maintenance/preflight.php
# After separate staging DB has been created/imported and credentials are stored privately:
php /path/to/domain-root/secure/maintenance/preflight.php --check-db
```

The preflight tests runtime, safe private config placement, flag states and (with `--check-db`) **read-only SELECT/schema checks**. It deliberately does **not** send email, write any lead, apply migrations, prove HTTPS externally or disclose credential values. A missing prerequisite is a BLOCKER rather than a false success. The bundled offline/CI check is:

```bash
php secure-setup/maintenance/preflight.php --source-only
```

## Test execution / acceptance evidence

| Test | Exact acceptance criterion | Evidence to record (no secrets) |
|---|---|---|
| Public pages | All service pages and original logo load at HTTPS staging | Status code and screenshots |
| Security boundary | `/secure/`, `.env`, SQL, backup and config inaccessible externally; restricted staging indexing | 403/404 results, response headers |
| Database | Fresh staging `schema.sql`, or backed-up V7 DB with V8 migration exactly once; 4 tables | Preflight pass and applied migration ID |
| Lead consent | Without consent / invalid payload / rate-limit fails; valid synthetic lead inserts exactly once | Test case ID, DB row count |
| Admin access | CSRF rejects, wrong password rejects, login/out works, password change invalidates old session; owner TOTP setup, fresh code, stale code and loss/recovery drill if MFA enabled | Redacted test log |
| Partner access | Unauthorized cases and PII inaccessible; disabled partner loses access; cannot start work pre-approval | Redacted test case transitions |
| Quote / approval | Positive approved quote plus minimal external evidence method/reference required; audit event present | Synthetic case ID and event type |
| Customer link | Minimal status, no PII or prices; expiry and rotation invalidate link | Status codes, **not tokens** |
| SMTP | Test message accepted **and delivered** to controlled inbox; DB lead survives SMTP failure | Redacted delivery log |
| Google Reviews | Disabled pending Google API approval | 503 and feature flags OFF |
| Recovery | Backup and restore test, restore timing recorded, production untouched | Timestamp, checksum |

The business owner must review the still-draft `/privacy/` content and authorize appropriate partner-data sharing and customer-data retention before enabling public submissions. Never deploy real customer credentials or live business leads to staging.

## Release gating

Do **not** enable `database.enabled`, `leads.enabled`, `portal.enabled` or public `leadSubmission` until relevant staging tests pass; keep `google.enabled` / `googleReviewsApi` OFF until approved. Do **not** deploy or overwrite production without a verified backup and explicit approval for the exact live target.

Before production: explicitly confirm whether owner TOTP is enabled (default OFF). If enabled, check server clock, fresh/stale OTP behavior and an offline recovery drill without exposing the private secret. Partner MFA remains an open requirement for higher-assurance partner access.

## Additional V8.3 evidence source

The GitHub Actions `disposable-mariadb-integration` job provides reproducible **synthetic test-only** MariaDB/PHP behavior and owner/partner transition evidence. The job uses a disposable self-signed localhost TLS proxy plus `tests/ci_https_router.php` to **simulate hosting TLS termination**: this is NOT a real TLS/SSL test. It cannot replace the separate Hostinger staging acceptance items above. No CI test password is a production credential, and the ignored `secure/aaditechs-config.php` generated during CI must never be uploaded or committed.


## V8.4 queued SMTP release gate (additional to existing tests)

- [ ] Apply the V8.4 `notification_outbox` migration to a backed-up staging database; run read-only `preflight.php --check-db`.
- [ ] **Before enabling `mail.enabled=true`**, install PHPMailer privately and configure a test SMTP recipient privately. No send occurs from a public API request.
- [ ] Submit a synthetic lead, confirm exactly one outbox row and read-only worker statistics.
- [ ] Explicitly run the private worker using `--run` and verify test inbox **delivery**, not just SMTP acceptance.
- [ ] Test SMTP downtime: lead persists and outbox retries with backoff; review `dead` alerts and authenticated owner-only retry.
- [ ] Schedule private cron execution only after testing; monitor backlog and confirm duplicate alerts can be de-duplicated by lead reference.
- [ ] Confirm backup/restore contains leads **and** notification_outbox, plus private runtime config recovery; do not include secrets in backups made public.

## V8.5 retention-safety gate (not deployed)

- [ ] Set an approved integer `retention_days` in private config; no automatic deletion until retention and legal policy sign-off.
- [ ] Run `php secure/maintenance/prune.php` (or `--dry-run`) and verify it prints only an eligible count without database writes.
- [ ] With synthetic staging rows, confirm jobs and pending/sending/dead alerts prevent deletion. Verify one expired unassigned lead with a sent alert is eligible.
- [ ] Record a verified staging DB backup before any `--run`. Confirm the exact count changed and backups permit recovery.
- [ ] Review any existing Hostinger cron: previous no-argument `prune.php` invocations are now read-only. Never silently convert those schedules to `--run`.

## V9–V10 additional release gates (all pending live acceptance)

- [ ] Resolve stacked PRs in dependency order and verify exact release SHA and CI. Back up staging and apply all missing migrations sequentially through V10.0; fresh DB uses full schema. Test restore.
- [ ] Owner-only Telegram authorization/replay, analytics consent, authorized discovery, growth pause, publication test destination and notification receipts.
- [ ] V9.7 TLS, worker token rotation, rate limiting, expired leases and actual local Ollama runtime.
- [ ] V9.8 actual private media render, matching manifest and owner review; no binary uploads or native AI video assumed.
- [ ] V9.9 static guide browser acceptance, local expert output safety and human review; no public live AI.
- [ ] V10.0 run source audit, review findings, register synthetic summary, test /seo and /seotopics. Search Console and live SEO acceptance separate.
- [ ] Confirm all features default OFF, local credentials private and no unintended publishing or outreach. Separate owner decision for production.

## V10.1 additional acceptance (pending)

- [ ] Apply V10.1 after V10.0 in release order. Verify official Meta app access, exact scopes, API versions, approved test identities and private token storage.
- [ ] Test target mismatch, revoked token, pause, approved revision, cancellation, ambiguous response and reconciliation before scheduling any authorized test publication. Confirm actual post independently. No Instagram/media acceptance implied.

## V10.2 additional acceptance (pending)

- [ ] Apply V10.2 after V10.1; verify LinkedIn administrator role/version, X user-context scopes/quota, GBP eligibility and account/location authorization. Keep all flags OFF until approved test destination.
- [ ] Verify real post receipt and visibility independently, ambiguous reconciliation and unified historical ledger counts. No YouTube/Instagram acceptance implied.

## V10.2A acceptance (pending runtime)

- [ ] Verify authenticated SFTP, private storage ACL, 20 MiB/memory cap, hash and stale manifest rejection, exact metadata/visibility/account approval, cancellation and no automatic retries. Confirm channel and test video processing/visibility independently; no Instagram media gate accepted.

## V10.2B acceptance (pending runtime)

- [ ] Apply V10.2B after V10.2A. Verify Meta Page/linked professional account, version, roles/scopes, real video format and disclosure, private transfer and processing status. Test mismatch, cancellation, expired container, ambiguous upload/publish, provider receipts and actual visibility separately. Explicit owner authorization for public test Reel required.

## V10.2C acceptance (pending runtime)

- [ ] Apply after V10.2B; verify Page role/scope/version, local media decoding, host size/memory, private hash/profile checks, text/media exclusion, wrong type/target, no uncertain retry, actual resource processing and visibility. Explicit public test authorization required.

## V10.2D acceptance (pending runtime)

- [ ] Apply after V10.2C. Verify LinkedIn admin and AVAILABLE owner, X user context and expiry, hash/target/text-image exclusion, uncertain reconciliation and real visibility. No video acceptance.

## V10.2E acceptance (pending runtime)

- [ ] Apply after V10.2D. Verify actual HTTPS positive/negative GET/HEAD, expiry, fetch cap, revocation/pause, log redaction and no caching, private JPEG format/crop, real Meta container readiness/visibility. Explicit public authorization. No carousel/video acceptance.

- [ ] V10.2E requires explicit fetch_log_redaction_verified=true only after real hosting/CDN log review. Verify Threads 1080x1080 and Instagram 1080x1350 JPEG; synthetic core fetch tests do not prove HTTP deployment.

## V10.2F acceptance (pending runtime)

- [ ] Apply after V10.2E. Verify real admin vs partner/anonymous access, password fingerprint/timeout, no-cache preview, file ACL/hash, bounded inventory, paused old-orphan quarantine and restore, reference protection and DB/filesystem audit recovery. No permanent purge.

## V10.2G acceptance (pending live)

- [ ] Apply after V10.2F. Enable lifecycle enforcement before live OAuth activation; test expired, revoked, malformed/missing metadata and rotation, sanitized read-only checks, stale evidence and actual provider scopes/renewal/revocation. No automated consent/refresh acceptance.

## V10.2H acceptance (pending runtime)

- [ ] Apply after V10.2G; verify persistent image resource receipt before post, audit failure blocks next provider action, crash reconciliation, no replay or token leaks. Actual video/carousel work pending.

## V10.2H.1 acceptance (pending runtime)

- [ ] Verify LinkedIn video format/rights and scopes, exact org, ranges/ETags, receipt persistence failure, separate AVAILABLE gate and actual visibility; Instagram receipt before binary, unified uncertain report, text/image/video exclusivity and storage protection. No automatic retry or other video/carousel acceptance.

## V10.2H.2 acceptance (pending runtime)

- [ ] Apply after V10.2H.1. Verify existing Meta image rows remain images, X scope and exact user, ordered chunks, durable ID before append, finalize/processing/expiry, exact public approval and no duplicate, Threads video type/hash and HTTPS GET/HEAD delivery, pause/cap/expiry/revocation, no caching/log leakage, container FINISHED and provider visibility. Validate real format and quotas, HTTP Range behavior, crash reconciliation and storage references. No carousel or partial-upload resume acceptance.

## V10.2H.3 acceptance (pending runtime)

- [ ] Apply after V10.2H.2. Verify 2–5 ordered JPEGs, wrong bundle and account rejection, duplicate reservations, child receipt persistence, parent and final readiness, no blind retry, child bearer expiry/cap/revocation, storage references, actual single-range 206/416 and memory limits, separate private inbox import and tampered pair rejection. Verify actual provider behavior and independent visibility. Mixed-video carousels and remote SFTP automation remain pending.

## V10.2I/J acceptance (pending runtime)

- [ ] Test dedicated SFTP account and pinned host key mismatch, permissions, exact remote asset pair and import hash checks, timeout/partial transfer recovery, destination rename behavior, no unintended publish. Verify owner-only daily expiry/stale/revoked summary, opt-out and period deduplication, all-ledger counts, actual schedule and delivery. Verify manual rotation and actual provider consent; automatic refresh remains pending.

## V10.2K/V10.3 acceptance (pending runtime)

- [ ] Verify Telegram business bot/channel identity, administrator post permission, real PNG/MP4 constraints, exact type/manifest/account approval, duplicate prevention, private file hash, receipt and independent visibility. Verify communications completed-day counts, extra-field rejection, conflict protection, missing data vs zero, pause, owner reports and retention policy. No live communications connectors, message bodies, call recordings or GBP media acceptance.

## V10.3A/V10.4 acceptance (pending runtime)

- [ ] Verify dedicated business Gmail consent and metadata scope, exact mailbox profile, INBOX system label counts, missing/expired/revoked token and generation change, stale snapshot, no raw responses, paused capture and actual owner report. Verify authorized discovery export source host, exact batch schema, all-or-none rollback, deduplication, source-run visibility, qualified-only reminder, date bounds, pause, completion/cancellation and no outreach. Browser dashboard, live WhatsApp/SMS/telephony and broader opportunity provider acceptance pending.

## V10.5 acceptance (pending runtime)

- [ ] Verify authorized owner HTTPS access, guest/partner rejection, session inactivity and fingerprint rotation, disabled flag, invalid dates/POST rejection, no-store/noindex/CSP, no customer fields or credentials, 7/30 completed UTC windows and current-status cohort labels, missing vs zero communications coverage, all-ledger and OAuth caveats, mobile table readability and DB/query performance on staging. Verify actual data sources separately. No dashboard mutation or release approval inferred.

## V10.5A/V10.6A acceptance (pending runtime)

- [ ] Verify prior schema baseline and missing migration order using release contract; encrypted/controlled backup and restore rehearsal in isolation; stop workers and pause growth before changes. Run read-only release-check.php --check-db; independently inspect types/indexes/FKs, data integrity, schema engine compatibility and privileges. Verify consented IST activity vs UTC aggregates, 7/30 windows, missing data, CSV owner-only and private headers, no sensitive values or spreadsheet formula injection, authorized communication batch dedupe/conflict rollback. Actual provider connectors, live credentials and production release remain pending.

## V10.6B acceptance (pending runtime)

- [ ] Verify Gmail consent and refresh grant, exact metadata scope, mailbox identity, expiry safety margin, unexpected refresh-token rotation refusal, paused growth, exclusive private candidate storage and manual activation/recovery. Verify approved 90-day communications and 30-day stale snapshot retention, dry-run no writes, changed digest rejection, cutoff boundary, transactional rollback, backup/restore and no unrelated deletion. Actual provider and hosting acceptance pending.

## V10.6C acceptance (pending runtime)

- [ ] Authorize aggregate source for each channel, confirm source count definitions and consent, TLS pinned public destination, separate bearer/HMAC secrets, exact signature/generation/freshness/channel/day and no personal data, idempotent import and conflicts/pause. Verify actual upstream integration separately. Compare release migration/fixture file integrity, confirm main baseline, stop workers, pause, reconcile unresolved worker/provider/lease/bearer rows, verify backup and isolated restore, review full cumulative candidate. No deployment or native provider acceptance inferred.

## V10.6D acceptance (pending runtime)

- [ ] Apply new review ledger migration in order after verified backup and isolated restoration. Check exact unknown row, independent provider evidence, state/evidence hash, pause and changed-state rejection, immutable review and unchanged publication status, release block for unreviewed/in-flight/processing rows. Verify private gzip artifact SHA256/CRC and actual restore/schema/data integrity independently. Verify Gmail candidate private permissions, expected digest, expiry, mailbox revalidation and manual rotation; no scope inference from identity. No live acceptance or release authorization inferred.

## V10.6E acceptance (pending runtime)

- [ ] Verify approved WABA account analytics permission and exact UTC window, sent/delivered semantics and actual response shape; authorized restricted Twilio usage-read key, exact account/category/unit/date, pagination refusal, missing versus zero and reporting lag. Confirm local expiry/generation and pause gates, no personal details stored, usage separate from answered/missed counts. Verify actual authorized isolated backup restore and schema/data integrity, not just synthetic CI. No live connector, inbound webhook, reply or release acceptance inferred.

## V10.6F acceptance (pending runtime)

- [ ] Verify actual two-item Telegram album format, exact digest/order/caption/channel approval, exclusive revision, provider group/two message IDs, memory limit and independent visibility. Verify Meta HTTPS subscription challenge and raw-body HMAC, exact WABA/phone, valid signature and tampering, duplicate deliveries, old/future events, pause/retry behavior, bounded body and storage cap, no raw content in hosting/CDN/APM logs, authorized retention and key-rotation procedure. No complete inbox coverage, native SMS/telephony webhooks or automatic replies inferred.

## V10.6G acceptance (pending runtime)

- [ ] Verify dedicated Twilio account/destination, authentic signed form and exact callback URL, URL/encoding variants and duplicate-key refusal, SMS-only and terminal inbound voice status filters, empty SMS response and voice 204 without routing change, duplicates across UTC receipt days, pause, actual delivery/retry and log redaction. Approve replay/retention policy before enabling; classic forms lack signed freshness. Verify WhatsApp 30-day cutoff, bounded dry-run/changed plan/rollback, backup and no Twilio deletion. Actual provider and staging acceptance pending.

## V10.6H acceptance (pending runtime)

- [ ] Verify old JPEG-only bundle approvals survive schema migration; new typed bundle requires one approved MP4, distinct assets, exact account/order and independent mixed flag. Verify actual Instagram/Threads child API behavior, codecs/aspect/duration and complete video fetch before revocation, parent readiness and receipt. Verify communication review reminders only from imported missed counts, owner status transitions, pause and retention guard. Actual provider access, backup/restore and staging release remain pending.

## V10.6I acceptance (pending runtime)

- [ ] Verify authorized Aaditechs property and dedicated Google read-only scope, exact OAuth response scope/expiry and property permission, no credentials or raw payload logs; confirm finalized web search period and date row behavior, weighted position, missing coverage, stale/rotated snapshot rejection, pause before network/commit, private owner report. Actual live Search Console consent, data accuracy, live crawl, other provider renewal, isolated backup/restore and staging release remain pending.

## V10.6J acceptance (pending runtime)

- [ ] Verify correct canonical property, authorized read-only grant, 7/30 PT date coverage, overlapping captures and revised day replacement, generation separation, old observations and cap/retention policy. Verify live robots policy, fixed seven paths, TLS/public IP and nonredirect behavior, partial failure/missing metrics and private aggregate report import. Confirm no indexing/ranking or uptime claims from HTML observations. Actual authorized crawl, provider evidence, AI runtime and staging restore remain pending.

## V10.6K acceptance (pending runtime)

- [ ] Verify local Ollama trusted process, installed digest, actual structured synthetic inference and model/codec resource limits, egress isolation and real draft quality. Verify approved SEO 180-day PT retention, dry-run, changed-plan rejection, cutoff and restoration. Verify authorized exact GBP account/location scoped media list, response shape and permission; independently approve upload access and authentic media policy before any write adapter. Actual local inference, native video generation, GBP write, Hostinger backup/restore and staging remain pending.

## V10.6L acceptance (pending runtime)

- [ ] Verify source authorization, exact feed schema and matching rules, source admission lock, pause during fetch and between records, cadence across failed/completed attempts, unresolved-run blocking, local batch rollback and operator recovery with stale/changed digest refusal. Confirm actual workers stopped and imported evidence reviewed, no automatic retry or outreach. Verify actual scheduling, source terms, hosted backup/restore and staging separately.

## V10.6M acceptance (pending runtime)

- [ ] Verify 126 service/channel combinations, exact objective plan, stale digest, existing/rejected cells, pause, transaction rollback, owner-only inventory and separate generation/review/publication. Verify Twilio receipt metadata compaction, retained keyed replay hash, duplicate replay after cleanup, cutoff/cap and restored tables. Confirm real callback configuration, approved retention, trusted local AI quality, actual workers stopped and hosted restore before activation. 126 synthetic cases are test coverage, not 126 deployed features or generated marketing assets.

## V10.6N acceptance (pending)

- [ ] In isolated staging only, verify plan limit 1–126, selected cell count, changed digest/limit/objective refusal, rejection and regenerated revision behavior, invalid UTF-8 and atomic failure; confirm no generation/publishing from batch CLI. Synthetic CI is not hosted acceptance. See V106N_BOUNDED_CREATIVE_REVIEW.md.

## V10.6O acceptance (pending)

- [ ] With synthetic authorized test feed, confirm schedule-check is no-network/no-DB, schedule-status is read-only, paused/unresolved/recent sources cannot fetch, and valid due source is imported only for owner review. Confirm private cron and log permissions before any actual scheduler installation; no outbound communications. See V106O_DISCOVERY_SCHEDULER_INTEGRITY.md.

## V10.6P acceptance (pending)

- [ ] On authorized isolated staging, test both reporting windows, all catalog rows, creative coverage states and overlapping categories, website missing/zero semantics, CSV privacy and responsive tables with owner/guest/partner sessions. No actual publishing or model calls. See V106P_OWNER_COVERAGE_INTELLIGENCE.md.

## V10.6Q acceptance (pending)

- [ ] Trusted local PC, exact tag/digest, actual structured inference, staging TLS/token, batch limits, interrupted/failed lease recovery and independent owner review. No live posting. See V106Q_PINNED_LOCAL_WORKER.md.

## V10.6R acceptance (pending)

- [ ] Capture reference from trusted fresh isolated instance, verify hash independently, compare restored staging metadata (table/column/index/FK), investigate drift, verify business data counts/integrity and actual restore under owner authorization. No production changes. See V106R_SCHEMA_RESTORE_GATE.md.

## V10.6S acceptance (pending)

- [ ] Test approved revisions exported privately, 1–12 batch plan/render, changed digest refusal, incomplete index recovery, actual per-bundle inspection and exact hash registration, all profile sizes, real local model/disk/performance and private permissions. No auto-upload. See V106S_REVIEWED_MEDIA_BATCHES.md.

## V10.6T acceptance (pending)

- [ ] Test isolated SFTP with approved 1–12 bundle index, all-item preflight, pinned host key, interruption recovery, exact remote file verification and individual server-side imports. Never assume SFTP acknowledgement is publication or approval. See V106T_REVIEWED_MEDIA_TRANSFER.md.

## V10.6U acceptance (pending)

- [ ] Authorized isolated host: validate plan without writes, apply reviewed digest, test disabled/pause/stale approval/tamper, destination conflicts, interrupted copy and audit recovery, private permissions and original source retention. See V106U_REVIEWED_SERVER_IMPORT.md.

## V10.6V acceptance (pending)

- [ ] Test isolated reference audit with missing/unsafe/hash-invalid files, all source projections, sample/budget limits, read-only plan eligibility, current references and existing quarantine destinations. Independently approve retention; existing single moves only. See V106V_MEDIA_INTEGRITY_AUDIT.md.

## V10.6W acceptance (pending)

- [ ] Verify actual authorized provider identity probes with reviewed scope, changed configuration rejection, expiry/revocation gates, pause and failure recovery, sanitized evidence. Renewal and provider permissions separate. See V106W_OAUTH_HEALTH_BATCH.md.


## V10.6X — reviewed opportunity follow-up batches (source only)
Private OFF-by-default 1–50 reminder set/reschedule/complete/cancel batches, read-only exact-state plan, digest-bound atomic apply, per-item audit, single-action audit rollback and 73 scope + 84 service regression cases. No external outreach or production changes. See [V10.6X](V106X_REVIEWED_FOLLOWUP_BATCH.md) for operator gates; CI and actual staging pending.


## V10.6Y — reviewed communications and retention integrity (source only)
New default-OFF private 1–50 aggregate review batch across email, WhatsApp, SMS and calls; exact-state plan/apply, per-item audit, local rollback and full-state retention digest. 108 scope and 24 channel scenarios authored, plus retention drift tests. Prior V10.6X CI passed. New CI and actual hosting/consent/retention acceptance pending. See [V10.6Y](V106Y_COMMUNICATION_REVIEW_BATCH.md).


## V10.6Z — private page-level SEO diagnostics (source only)
Optional detailed seven-page observation v2 with strict path/status/finding consistency, aggregate-only persistence, v1 compatibility, atomic Search Console snapshot/history/audit and live SEO audit rollback. 56 crawler + 126 report validation scenarios authored. Prior V10.6Y CI passed; new CI and actual crawl/provider/staging acceptance pending. See [V10.6Z](V106Z_PAGE_SEO_DIAGNOSTICS.md).


## V10.7A — competitor editorial evidence (source only)
Offline private operator-supplied own/peer comparison across 14 services and six editorial signals (84 cells), 1–5 peers, explicit unknown evidence, source references and review-only gap suggestions, 504 synthetic decision cases. No scraping, actual competitor claims, rankings, publishing or database changes. Prior V10.6Z CI passed; new CI and real evidence acceptance pending. See [V10.7A](V107A_COMPETITOR_EVIDENCE.md).


## V10.7B — reviewed editorial task batches (source only)
Private default-OFF competitor-to-SEO review handoff, 1–84 selected cells, exact reviewed plan, stale digest rejection, atomic audited pending-review task creation and existing-task preservation. 84 export + 168 scope cases and synthetic 84-task database test authored. No live competitor data, external publishing or production changes. See [V10.7B](V107B_COMPETITOR_REVIEW_TASKS.md).


## V10.7C — restore row-count reconciliation (source only)
Private read-only count capture/compare across 49 contract tables; exact metadata/backup binding, canonical reference, table-specific match/drift, pause and worker gates, backup FIFO/size safeguards. 98 table-specific scenarios and disposable DB count checks authored. Prior V10.7B CI passed; new CI and actual isolated restore pending. See [V10.7C](V107C_RESTORE_COUNT_RECONCILIATION.md).


## V10.7D — keyed restore content fingerprints (source only)
Private bounded read-only content fingerprint comparison across 49 tables; keyed per-table HMAC with primary-key order, binary field framing, metadata/backup binding and canonical reference. 98 table cases and same-count mutation regression authored. Prior V10.7C CI passed; new CI and actual isolated restore pending. See [V10.7D](V107D_RESTORE_CONTENT_FINGERPRINTS.md).


## V10.7E — private media restore parity (source only)
Full bounded active/quarantine asset verification and keyed parity across 15 reference sources, missing-reference readiness gate, no raw asset names exported, three staging flag visibility updates. 51 manifest matrix cases authored. Prior V10.7D CI passed; actual restoration and new CI pending. See [V10.7E](V107E_MEDIA_RESTORE_PARITY.md).


## V10.7F — staging configuration and schema preflight (source only)
113 explicit flag states, 76 child flag dependency checks, strict all-off isolated staging baseline, all 49 contract tables/required columns and 437 matrix cases authored. Prior V10.7E CI passed; new CI and actual hosted acceptance pending. See [V10.7F](V107F_STAGING_PREFLIGHT.md).


## V10.7G — exact deployment integrity (source only)
Commit-derived bounded SHA-256 inventory of all tracked public files/private maintenance scripts, private staged-copy parity, missing/drift/extra/unsafe checks, preflight config path and permission gate, 102 synthetic file cases. Prior V10.7F CI passed; new CI and actual hosting pending. See [V10.7G](V107G_DEPLOYMENT_INTEGRITY.md).


## V10.7I — local native video review candidates (source only)
Offline fixed CogVideoX candidate adapter, reviewed 1–12 job/model/font/seed plan, 14-service/9-channel coverage, bounded private motion rendering and review-only index, no transfer or publication. 126 plan cases and synthetic moving-frame codec tests authored. Prior V10.7H CI passed; actual GPU/model, new CI and hosted acceptance pending. See [V10.7I](V107I_NATIVE_VIDEO_CANDIDATES.md).

V10.7I follow-up adds exact read-only candidate re-verification, complete index/bundle and per-job binding, asset integrity and explicit editorial disposition report with publication_authorized=false. No server import or actual review. Final-head CI pending.


## V10.7J — reviewed OAuth rotation and native-video CI correction (source only)
Seven provider replacement credentials can be privately planned and identity-verified without activation. 84 provider cases authored; separate default-OFF flag, HMAC binding, pause/config gates and fail-stop. Corrects V10.7I font fixture without relaxing production checks. New CI pending; actual provider renewal, GPU inference and hosted acceptance pending. See [V10.7J](V107J_OAUTH_ROTATION_REVIEW.md).


## V10.7K — business-facing website completion (source only)
Nine new dedicated service pages; catalog coverage 5/14 to 14/14, static homepage navigation, sitemap/analytics/live SEO coverage 16 public paths, first-load improvements and no-JS inquiry safety. Real performance measurement, content sign-off and deployment pending. See [V10.7K](V107K_SERVICE_WEBSITE.md).


## V10.7L — competitor-informed service conversion (source)
Public editorial benchmark of 10 pages (7 page-text, 3 indexed excerpts), 14 service-specific proposal checklists (56 topics), 14 local discussion brief builders and related navigation. No fabricated claims, no automated sending or new collection. V10.7K final CI passed; V10.7L CI and hosted acceptance pending. See [V10.7L](V107L_COMPETITOR_RESEARCH_AND_CONVERSION.md).


## V10.7M — tender discovery and review (source)
Eight dated public observations (six listed candidates, one unconfirmed, one archived); fixed official source registry, offline 14-service tender shortlist and selected pending research-task bridge. Default OFF, no actual bid/outreach or live import. V10.7L all three CI passed; new CI and automated authorized ingestion pending. See [V10.7M](V107M_TENDER_DISCOVERY.md).


## V10.7N — scheduled tender ingestion (source)
Authorized normalized HTTPS tender feed worker (1–5 sources, 25 notices/source), optional private bearer, reviewed service routing, cadence, atomic pending research task/ledger write and owner queue. V10.7M all CI passed. No actual provider connection or cron installed; final CI pending. See [V10.7N](V107N_SCHEDULED_TENDER_INGESTION.md).


## V10.7O — inquiry response and automatic communication review (source)
Private owner oldest-first website inquiry response queue, status-based internal response target, explicit response recording, aggregate-only owner reporting, automatic missed-channel internal review reminders across four channels. Both new flags default OFF. No actual message or cron installed. Source CI and live acceptance pending. See [V10.7O](V107O_COMMUNICATION_WORKFLOWS.md).


## V10.7P — Google OAuth continuity (source)
Automatic ephemeral access-token refresh for scheduled Gmail metadata and Search Console read-only capture; no active credential file mutation, reviewed grant expiry/revoke and exact scope, per-provider lock and freshness skip. Both default OFF; V10.7O all CI passed. Actual consent, live Google capture and hosted cron pending. See [V10.7P](V107P_GOOGLE_OAUTH_CONTINUITY.md).


## V10.7Q scheduled Google capture safety acceptance (pending live staging)
- [ ] Verify revocation or growth pause between identity and aggregate requests stops the second request; repeat with database configuration drift and token safety-window expiry using controlled test doubles, never real credential disclosure.
- [ ] Confirm old dated snapshot remains unchanged on refusal, private worker exits nonzero and no overlapping worker lock is left behind.
- [ ] Confirm exact live read-only provider consent and isolated scheduled capture separately. V10.7P source CI passed; V10.7Q CI pending. No production or hosting acceptance inferred.


## V10.7R full restore rehearsal (live acceptance remains pending)
- [ ] Verify final CI synthetic dump and media tar restoration, all 49 table counts/fingerprints and all 17 media zone/reference comparisons. CI is not hosted restore.
- [ ] Obtain independently recorded source database/media archive digests on frozen, isolated hosting; capture metadata/count/content/media references before restoring to an independent target.
- [ ] Compare actual restored target against source references, ensure no missing/quarantined referenced assets and complete application acceptance; record measured recovery time and any drift. Do not enable production or use a source database as its own hosted reference.


## V10.7S native video review bridge (pending live acceptance)
- [ ] Verify final source CI, actual local model provenance/rights, approved text export, human review and exact reviewed decision/packet SHA-256.
- [ ] Verify separate default-OFF flag, current approved source binding, read-only plan, atomic pending system-task apply, stale plan refusal and duplicate preservation on isolated staging.
- [ ] Confirm no registered media manifests or queued publication, no private asset or customer exposure. Native binary import and real GPU/provider acceptance remain separate; generic review task approval is not media approval.


## V10.7T native binary import (pending live acceptance)
- [ ] Verify final CI and reviewed backup/migration/fresh schema/restore parity for 37 migrations, 49 tables, 39 pinned hashes; apply only on isolated target.
- [ ] Verify exact original native handoff packet and owner-approved editorial task, current approved source, private SFTP inbox, asset hash/profile, read-only plan, stale plan refusal, separate default-OFF import gate and pending native-video media manifest.
- [ ] Verify full local decode and model provenance, source retained and replay preserves approval/rejection; reconcile partial exact files if failure. Explicit media and provider approval remain separate. No automatic provider action.


## V10.7U native private transfer (pending live acceptance)
- [ ] Verify final CI and separately approved SFTP destination, private fresh inbox, pinned host key and OpenSSH hardlink extension; no overwrite fallback.
- [ ] Verify original reviewed packet, actual local full decode, separately reviewed transfer plan, exact private spool, remote chmod and SHA-256 readback. Inspect partial/final links and reconcile interruption without automatic retry.
- [ ] Verify actual server owner approval and separate V10.7T import on isolated staging. No publication from transfer; real provider acceptance remains separate.


## V10.7V Google social renewal (pending live acceptance)
- [ ] Verify final CI and independently authorized Google Business and YouTube exact scope grants, target identity, review expiry and private candidate directory.
- [ ] Verify due/skip/pause/revocation gates, fixed HTTPS exchange, candidate private permissions, no overwrite or active credential mutation on isolated staging.
- [ ] Review V10.7J HMAC rotation candidate and separately authorize active rotation; verify no token logging, no automatic activation, no publication, and no unsupervised cron during live operations. Other providers remain pending.


## V10.7W Meta renewal (pending live acceptance)
- [ ] Verify final CI and independent Meta app/user/Page or Threads long-lived consent, exact target, scope, provider expiry and private storage.
- [ ] Verify paused maintenance, distinct candidate, scope/identity and grant drift refusal, no overwrite, and no active credential mutation. Threads same-token response and missing debug fields require manual review.
- [ ] Verify separate V10.7J rotation approval, no token logging and no publication. Actual provider responses, hosted cron and LinkedIn/X remain open.


## V10.7X LinkedIn/X renewal (pending live acceptance)
- [ ] Verify CI, LinkedIn programmatic-refresh eligibility and confidential-client authorization, exact scopes/target, refresh expiry and private storage.
- [ ] Verify paused maintenance, attempt marker before exchange, no retry after uncertain response, separate private unverified refresh response, per-request identity and expiry checks and exact rotation candidate.
- [ ] Independently reconcile any rotated refresh token, review and separately authorize active credential rotation; verify no automatic activation or publication, no token logging and protected journal retention. Hosted cron and live provider acceptance remain open.
