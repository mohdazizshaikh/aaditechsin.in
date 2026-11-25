# Aaditechs — Verified progress / fresh-agent handoff

**Historical V8 baseline:** V8.5 code and disposable-MariaDB tests verified on 23 November 2026; see current GitHub `main` for the exact latest commit. Previous V7 SHA `ea67fa26c558d01640139ac5eecd00c5c2a45623`. Previous V6 GitHub baseline SHA `43bba82c0d75fb842c1e928eec5d9aa85ab7dbf0`. This file describes source completeness, **not production uptime**. Read the repository and confirm the actual Git HEAD before coding or deploying.

## Current development baseline — 25 November 2026

Production is unchanged. Main remains V9.6 at verified commit 7582be3c9beafa7335c1ced9bc9c6369b4360daf (CI 45380755788). V9.7 onward are stacked unmerged development PRs. Verify current Git HEAD and CI before proceeding; this is a dated evidence snapshot, not a live status feed.

| Milestone | Source / evidence | Remaining |
|---|---|---|
| V9.1–V9.6 | [Main consolidated CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45380755788) | Live provider, owner bot, privacy and staging acceptance |
| V9.7 | [PR #3](https://github.com/mohdazizshaikh/aaditechsin.in/pull/3), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45443108590) | HTTPS endpoint, local Ollama runtime, scheduling |
| V9.8 | [PR #4](https://github.com/mohdazizshaikh/aaditechsin.in/pull/4), [corrected CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45447300543) | Real diffusion, native video, automatic transfer and binary preview |
| V9.9 | [PR #5](https://github.com/mohdazizshaikh/aaditechsin.in/pull/5), [corrected CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45449569981) | Live AI, consented free text, customer-specific qualification |
| V10.0 | [PR #6](https://github.com/mohdazizshaikh/aaditechsin.in/pull/6), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45449791408) | Live SEO crawl, Search Console, competitor evidence, scheduling |
| V10.1 | [PR #7](https://github.com/mohdazizshaikh/aaditechsin.in/pull/7), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45450797137) | Facebook/Threads live authorization, Instagram/media transport, token lifecycle and insights |
| V10.2 | [PR #8](https://github.com/mohdazizshaikh/aaditechsin.in/pull/8), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45452061745) | LinkedIn/X/GBP text adapters, unified reporting; live OAuth and media uploads pending |
| V10.2A | [PR #9](https://github.com/mohdazizshaikh/aaditechsin.in/pull/9), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45453713017) | Manual private SFTP, verified MP4 staging, immutable YouTube upload approval and mock provider flow; actual OAuth, Instagram and real uploads pending |
| V10.2B | [PR #10](https://github.com/mohdazizshaikh/aaditechsin.in/pull/10), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45454207277) | Direct Reel upload and separate processing/publish gates, real provider/media format acceptance pending |
| V10.2C | [PR #11](https://github.com/mohdazizshaikh/aaditechsin.in/pull/11), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45454875369) | Facebook photo/video direct multipart, exclusive revision reservation, local media decode validation; live acceptance pending |
| V10.2D | [PR #12](https://github.com/mohdazizshaikh/aaditechsin.in/pull/12), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45455600163) | LinkedIn/X single PNG upload and attachment; video and live acceptance pending |
| V10.2E | [PR #13](https://github.com/mohdazizshaikh/aaditechsin.in/pull/13), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45456657400) | Threads/Instagram single JPEG, short-lived bearer delivery and explicit publish, 4:5 IG derivative and positive core fetch tests; live endpoint/log redaction, carousels, video pending |
| V10.2F | [PR #14](https://github.com/mohdazizshaikh/aaditechsin.in/pull/14), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45457164812) | Owner-only preview, bounded inventory, reversible old-orphan quarantine and audit; live browser/storage acceptance and policy purge pending |
| V10.2G | [PR #15](https://github.com/mohdazizshaikh/aaditechsin.in/pull/15), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45458155252) | Local expiry/revocation gates and sanitized read-only health, manual rotation; automatic OAuth refresh/consent pending |
| V10.2H | [PR #16](https://github.com/mohdazizshaikh/aaditechsin.in/pull/16), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45458986812) | Durable intermediate LinkedIn/X image receipts and reconciliation report. Video/carousel adapters still pending |
| V10.2H.1 | [PR #17](https://github.com/mohdazizshaikh/aaditechsin.in/pull/17), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45460232162) | LinkedIn multipart video, durable Reel container, expanded recovery report and storage protection; live acceptance and X/Threads video/carousels pending |
| V10.2H.2 | [PR #18](https://github.com/mohdazizshaikh/aaditechsin.in/pull/18), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45461132195) | X chunked MP4, Threads expiring MP4 fetch, exact type approval and recovery; carousels and live provider acceptance pending |
| V10.2H.3 | [PR #19](https://github.com/mohdazizshaikh/aaditechsin.in/pull/19), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45462324677) | Ordered Instagram/Threads 2–5 JPEG carousel, durable children, bounded video GET ranges, private inbox import; live API/HTTP and remote SFTP automation pending |
| V10.2I/J | [PR #20](https://github.com/mohdazizshaikh/aaditechsin.in/pull/20), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45463023115) | Explicit pinned SFTP delivery, private renderer permissions, daily OAuth attention and all-ledger report; real transport/cron/automatic credential refresh pending |
| V10.2K/V10.3 | [PR #21](https://github.com/mohdazizshaikh/aaditechsin.in/pull/21), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45463561611) | Telegram single photo/video, aggregate communications import and daily owner reporting. Live connectors, GBP media, mixed carousels and automatic OAuth refresh pending |
| V10.3A/V10.4 | [PR #22](https://github.com/mohdazizshaikh/aaditechsin.in/pull/22), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45478299522) | Gmail INBOX metadata snapshot, authorized batch discovery, qualified internal reminders and consolidated owner report. Actual Gmail consent, WhatsApp/SMS/telephony, live feeds and browser dashboard pending |
| V10.5 | [PR #23](https://github.com/mohdazizshaikh/aaditechsin.in/pull/23), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45479007238) | Read-only owner web dashboard, 7/30 completed UTC days, inquiry cohort statuses, opportunity service pipeline, review queues, communications coverage/trends, publication and credential health. Live acceptance and advanced analytics pending |
| V10.5A/V10.6A | [PR #24](https://github.com/mohdazizshaikh/aaditechsin.in/pull/24), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45480184211) | Consented IST website trends, aggregate owner CSV, atomic communication batch import, ordered 28-file migration inventory, V9.6 upgrade replay and 40-table read-only release check. Live connectors, migration/backup rehearsal and staging pending |
| V10.6B | [PR #25](https://github.com/mohdazizshaikh/aaditechsin.in/pull/25), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45480712302) | Explicit metadata-only Gmail token renewal candidate with identity check, private storage, paused digest-confirmed communications retention; automatic activation, live consent and retention approval pending |
| V10.6C | [PR #26](https://github.com/mohdazizshaikh/aaditechsin.in/pull/26), [incremental CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45481759525), [draft cumulative PR #27 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45481764527) | Signed authorized aggregate HTTPS bridge for WhatsApp/SMS/calls, migration file integrity and read-only worker-state release blockers. Native provider adapters, live source acceptance, reviewed reconciliation, backup/restore and deployment pending |
| V10.6D | [PR #28](https://github.com/mohdazizshaikh/aaditechsin.in/pull/28), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45482909441) | Immutable unknown publication owner review, exact current-state digest and evidence hash, private gzip backup verification, Gmail candidate pre-activation revalidation. Actual reconciliation, isolated restore, automatic activation and release pending |
| V10.6E | [PR #29](https://github.com/mohdazizshaikh/aaditechsin.in/pull/29), [incremental CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45484005129), [cumulative draft #30 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45484011358) | Read-only WhatsApp Business analytics and Twilio inbound SMS/voice usage, separately labeled owner snapshots, synthetic MariaDB dump/restore round trip. Actual provider consent, inbound message workflows, real backup restoration and staging pending |
| V10.6F | [PR #31](https://github.com/mohdazizshaikh/aaditechsin.in/pull/31), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45485373946) | Exact PNG+MP4 Telegram album, separate owner approval, group/message receipts; signed WhatsApp inbound event counting, account binding, deduplication and owner reports. Live webhook consent/log redaction/retention, SMS and telephony inbound workflows and arbitrary albums pending |
| V10.6G | [PR #32](https://github.com/mohdazizshaikh/aaditechsin.in/pull/32), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45486092520) | Signed Twilio SMS and terminal inbound voice callback counts, owner reporting, digest-bound WhatsApp cleanup. Live callback routing/consent, Twilio replay-safe retention, mixed media and staging pending |
| V10.6H | [PR #33](https://github.com/mohdazizshaikh/aaditechsin.in/pull/33), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45487025977), [cumulative draft #34 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45487031992) | Typed JPEG+approved MP4 Meta carousel children, legacy image digest compatibility, internal missed-aggregate review reminders and retention guard. Actual media/provider acceptance, outbound follow-up and staging pending |
| V10.6I | [PR #35](https://github.com/mohdazizshaikh/aaditechsin.in/pull/35), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45487853308) | Read-only Search Console refresh grant and exact property verification, finalized seven-day web search aggregate, owner reports and dashboard. Actual consent/property and provider response acceptance, live crawl, other OAuth activation and GBP media pending |
| V10.6J | [PR #36](https://github.com/mohdazizshaikh/aaditechsin.in/pull/36), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45488781927) | Canonical property allowlist correction, daily Search Console history and 7/30-day owner table, bounded seven-page public HTML crawler and private aggregate import. Actual property consent, live crawl, retention, GBP media and local AI runtime pending |
| V10.6K | [PR #37](https://github.com/mohdazizshaikh/aaditechsin.in/pull/37), [incremental CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45489895464), [cumulative draft #38 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45489903977) | Exact local model inventory/digest and optional synthetic inference/codec diagnostics, paused digest-confirmed SEO history retention, GBP scoped media-list read readiness. Actual local model/native video, upload permission/publishing and real restoration pending |
| V10.6L | [PR #39](https://github.com/mohdazizshaikh/aaditechsin.in/pull/39), [both CI jobs passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45490967375) | Transactional feed admission, pause/recovery checks before import, opt-in per-source cadence, reviewed interrupted-run recovery and atomic local batch. Actual authorized feeds, scheduler installation, GBP media write and native AI video pending |
| V10.6M | [PR #40](https://github.com/mohdazizshaikh/aaditechsin.in/pull/40), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494228698), [draft #41 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494242167) | Exact-plan creative batch up to 126 service/channel draft requests per objective, 126 synthetic workflow cases, replay-preserving Twilio metadata compaction and shared release ledger inventory. Actual AI generation, provider acceptance, owner retention approval, GBP media write and hosting restore pending |
| V10.6N | [PR #42](https://github.com/mohdazizshaikh/aaditechsin.in/pull/42), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494801920), [draft #43 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494808181) | Exact scoped chunks, UTF-8 and catalog guards, atomic result import, 126 new synthetic review-path cases; actual generation/provider/hosting acceptance pending |
| V10.6O | [PR #44](https://github.com/mohdazizshaikh/aaditechsin.in/pull/44), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45496537252), [draft #45 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45496555590) | Separately gated scheduler entry, read-only due status, UTF-8 guard, atomic opportunity/draft audit and 126 evidence cases; actual cron and authorized provider acceptance pending |
| V10.6P | [PR #46](https://github.com/mohdazizshaikh/aaditechsin.in/pull/46), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45498426102), [draft #47 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45498432910) | 56 creative objective/service aggregate rows, complete opportunity catalog, 27 website breakdown categories, private CSV; actual hosting/provider acceptance pending |
| V10.6Q | [PR #48](https://github.com/mohdazizshaikh/aaditechsin.in/pull/48), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45499069195), [draft #49 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45499077052) | Exact catalog, strict JSON, bounded 1–20 job poll, digest and response identity checks, 56 creative positive/tamper pairs plus 28 expert cases; actual model/runtime/scheduler acceptance pending |
| V10.6R | [PR #50](https://github.com/mohdazizshaikh/aaditechsin.in/pull/50), [incremental CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560435935), [draft #51 cumulative CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560440719) | Read-only trusted reference metadata comparison, 49 table drift cases, private gzip identity safeguards; actual hosting restore and reference capture pending |
| V10.6S | [PR #52](https://github.com/mohdazizshaikh/aaditechsin.in/pull/52), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560708189), [draft #53 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560712922) | 1–12 local verified bundles per exact job plan, strict input and asset verification, 126 service/channel validation cases; actual AI/native video and hosted acceptance pending |
| V10.6T | [PR #54](https://github.com/mohdazizshaikh/aaditechsin.in/pull/54), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568204750), [draft #55 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568209111) | Exact completed index, all-bundle preflight, bounded SFTP delivery, stop-on-uncertainty, 126 profile integrity cases; actual SFTP/import and provider acceptance pending |
| V10.6U | [PR #56](https://github.com/mohdazizshaikh/aaditechsin.in/pull/56), [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568784213), [draft #57 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568789959) | Independent OFF gate, 1–12 approved bundles, read-only plan, digest and repeat preflight, audited apply, 84 scope cases; actual hosting import pending |
| V10.6V | [PR #58](https://github.com/mohdazizshaikh/aaditechsin.in/pull/58), [incremental CI](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45569155055), [draft #59 cumulative CI](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45569161982) | 15 reference projections, bounded reverse audit, read-only 1–50 asset quarantine plan, 100 request cases; actual retention and hosting acceptance pending |
| V10.6W | [PR #60 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45569454303), [draft #61 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45569458258) | Strict lifecycle policy, independently gated 1–7 provider health plan and check, 77 policy cases; actual consent/refresh/revocation pending |
| V10.6X | [PR #62 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45578648418), [draft #63 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45578657826) | 1–50 exact-state reminder batch, atomic audited apply, 73 scope + 84 per-service cases; actual hosting and owner acceptance pending |
| V10.6Y | [PR #64 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45607405958), [draft #65 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45607412363) | 1–50 channel/day reviews, full-state retention digest, atomic audit, 108 scope + 24 channel cases; actual consent and hosting pending |
| V10.6Z | [PR #66 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45611270486), [draft #67 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45611277772) | Seven fixed-page diagnostics, strict aggregate projection, atomic SEO captures, 56 crawler + 126 contract cases; actual crawl/provider acceptance pending |
| V10.7A | [PR #68 CI](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45633788862), [draft #69 CI](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45633800529) | 14-service/6-signal comparison, 1–5 peer sites, 504 synthetic decision cases; real authorized evidence and editorial review pending |
| V10.7B | [PR #70 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45634148688), [draft #71 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45634157251) | 1–84 pending-review SEO tasks, exact plan and preservation, 84 export + 168 scope cases; actual owner evidence/hosting pending |
| V10.7C | [PR #72 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45636945746), [draft #73 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45636959590) | 49-table private count comparison, exact contract/metadata/backup binding, 98 table-specific checks; actual isolated restore pending |
| V10.7D | [PR #74 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45637881776), [draft #75 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45637889102) | 49-table keyed content comparison, bounded binary projection, 98 table-specific checks; actual isolated restore pending |
| V10.7E | [PR #76 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45638451736), [draft #77 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45638461550) | Full bounded active/quarantine inventory, 15 reference sources, 51 matrix cases, availability gate; actual media restore pending |
| V10.7F | [PR #78 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45649879579), [draft #79 CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45649892096) | 113 explicit flags, dependency consistency, all-off baseline and 49-table column coverage; actual hosting pending |
| V10.7G | Commit 683220ba78b70b9d8865212c7cf36ea715580f06; inherited V10.7H CI passed | Exact commit-derived public and private maintenance parity, restricted config paths, 102 file cases; actual hosting pending |
| V10.7H | [Initial CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45650983018), [final CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45651129083) | Sixteen decode calls hardened, 200 matrix cases, six scope integrations, development branch CI trigger; actual hosting pending |
| V10.7I | Font fixture corrected in V10.7J; inherited CI passed | Offline fixed CogVideoX adapter, reviewed 1–12 job batch, nine profiles, private verification and editorial disposition; real GPU/model and publication integration pending |

Priority backlog: P0 source CI and authorization; P1 verify private media staging and YouTube upload, Instagram direct Reel runtime acceptance, Facebook media runtime, Threads/X/LinkedIn video runtime, Instagram and Threads carousel runtime, mixed video carousels, live pinned SFTP delivery and import acceptance, bearer endpoint acceptance, private preview/retention runtime, referenced asset retention policy, actual OAuth consent, automatic refresh/revocation, live daily alert delivery and provider acceptance; P1 authorized Gmail metadata runtime, native WhatsApp/Twilio reporting runtime, consented WhatsApp inbound runtime and retention, native SMS/telephony callback runtime, replay-safe retention and follow-up adapters and authorized aggregate bridge runtime, consent/retention and GBP media (API approval required); P1 authorized discovery feed runtime; P1 owner dashboard staging acceptance and actionable lead intelligence; final isolated staging and production release gate. Do not mark full social integration complete until media, OAuth, live receipts and reconciliation accepted.

Next phases: V10.3 communications, V10.4 discovery providers, V10.5 dashboard, V10.6 staging and release. Native AI video, actual local model tests, public AI and provider acceptance remain open independently. No real outreach, posts, production changes or credentials in Git. All new flags OFF.

Canonical tracker: [issue #2](https://github.com/mohdazizshaikh/aaditechsin.in/issues/2); [release gate #1](https://github.com/mohdazizshaikh/aaditechsin.in/issues/1). Module docs V9_GROWTH_FOUNDATION, V92_ANALYTICS, V93_DISCOVERY, V931_SOURCE_ADAPTER, V94_CREATIVE, V95_V96_OPERATIONS, V97_CREATIVE_TRANSPORT, V98_MEDIA_STUDIO, V99_TECHNICAL_EXPERT, V100_SEO, V101_META, V102_NETWORKS, V102A_MEDIA_UPLOAD, V102B_INSTAGRAM, V102C_FACEBOOK_MEDIA, V102D_NETWORK_MEDIA, V102E_META_IMAGES, V102F_MEDIA_STORAGE, V102G_OAUTH_HEALTH, V102H_MEDIA_RECOVERY, V102H1_VIDEO_BATCH, V102H2_VIDEO_BATCH, V102H3_CAROUSEL_TRANSFER, V102IJ_TRANSFER_HEALTH, V102K_V103_COMMUNICATIONS, V103A_V104_INTELLIGENCE, V105_OWNER_DASHBOARD, V105A_V106A_RELEASE_READINESS.

## V8 audit additions (local source, not live acceptance)

- [x] Partner cannot start `in_progress` without owner-recorded customer approval; assessment-only notes now work.
- [x] Admin approval evidence method/reference and a positive quoted amount are enforced server-side; staged progression controls and audit events added.
- [x] Partner password reset/disable invalidates live sessions on next request; owner admin hash changes invalidate owner sessions.
- [x] Customer tracking token TTL and origin validation; preexisting V7 DB requires documented V8 schema migration.
- [x] New standalone Data Recovery and IT AMC pages; local service routes and sitemap updated.
- [x] `tests/workflow_rules.php`, `tests/site_integrity.py`, `tests/http_smoke.py` plus prior browser/static tests; not substitutes for Hostinger DB end-to-end acceptance.
- [x] Updated V8 architecture, security checklist, deployment runbook and gap audit; no real secrets or user records in source.

## Implemented in source

- [x] Original orange-and-blue logo retained; IT and Data Recovery service categories preserved.
- [x] AV division homepage, filters, dedicated Video Conferencing, Auditorium AV and Professional Sound/PA pages.
- [x] AV project scope builder and honest no-fake-pricing copy.
- [x] Guided English/Hinglish chatbot for Data Recovery, AV, IT Support/AMC, Software and Hardware, with initial triage and consent-based inquiry. Deterministic logic, **not** a connected LLM or live support.
- [x] Hostinger MySQL schema: leads, partners, jobs, job_activity.
- [x] Hardened lead API: validated JSON, consent, HTTPS/same-origin checks, per-IP salted hash rate-limit and database-first persistence.
- [x] Owner dashboard: authenticated lead list, lead status, partner accounts/password reset/disable, work-order assignment, quote/cost fields and audit notes.
- [x] Partner login: assigned work only, limited status updates, no customer contact or owner financial details.
- [x] Customer case status: hashed random token, noindex, minimal fields, manual sharing; rotate invalidates older links.
- [x] Secrets template, schema, runbook, private storage documentation, sitemap/robots and offline browser/PHP lints.

## External dependencies / production blockers — not completed

- [ ] Confirm exact Hostinger account PHP version, PDO MySQL, SSL, writable private sibling folder, backups, DB access and Composer/PHPMailer availability.
- [ ] Privacy notice is a **DRAFT**; verify business data controller details, storage/retention, partner sharing and India DPDP requirements with owner/advisor.
- [ ] Create actual database on Hostinger, import `secure-setup/schema.sql`, set restricted DB account credentials in private config.
- [ ] Generate strong admin passphrase hash and secure partner onboarding. Run auth/session, CSRF, role access and logout tests over actual HTTPS.
- [ ] Configure SMTP and verify accepted notification, delivery and bounce behavior; never assume email is delivered just because SMTP accepted.
- [ ] Configure `retention_days` after policy review and schedule CLI cleanup; audit jobs before any deletion.
- [ ] Turn on feature flags (`database.enabled`, `leads.enabled`, `portal.enabled`, public `leadSubmission`) only after staging tests.
- [ ] Google Business Profile API: on 18 Nov 2026 the application was stopped at the 60-day eligibility criterion. Earliest cautious retry around 5 Dec 2026 if verification was on 4/5 Oct. Google's approval is still required; keep `google.enabled=false` and `googleReviewsApi=false` until then.
- [ ] Verify Google profile, business address, real phone numbers, service capabilities, rates and partner SLAs with owner before publishing.
- [ ] Hostinger **staging and production website** not deployed in this code task. Preserve existing production `aaditechs.in`; back up before any replacement.
- [ ] True LLM assistant (optional Ollama inference hosting) is not implemented; requires a separately secured inference service and privacy controls.
- [ ] Payment processing, real-time partner booking, automatic quotes, customer self-approval, ERP and Google analytics connectors are NOT implemented. These require separate confirmed requirements.

## Important decisions

- Aaditechs is a **coordinated IT + AV service partner network**; works with separate specialists for data recovery, software, hardware procurement, field IT support and audio visual. Never pretend all work is delivered directly by full-time Aaditechs employees.
- Owner controls final price/partner cost and customer commitments. Partner portal displays assigned work scopes but NOT customer contact fields or internal pricing.
- User explicitly asked to push complete code to `mohdazizshaikh/aaditechsin.in` via GitHub identity `mohd.aziz.sk@gmail.com`. Repository is **public**. Never push credentials or real lead exports.
- Google auth keys, SMTP passwords and database credentials must live outside `public_html` and Git.

## Source verification

- Run PHP/JS syntax and `tests/browser_smoke.py`; check all links and frontend demo-mode behavior.
- Confirm Git tree SHA and every changed file's Git blob SHA matches local files after push; avoid force pushes.
- Before any live deploy: reproduce staging setup; perform negative tests with public flags off; back up production and rollback assets.

## Still-open gaps after V8 fixes

- Hostinger staging and live PDO MySQL/SMTP/role tests not performed. Privacy page is still a DRAFT.
- No real Google OAuth integration approval, interactive LLM, 2FA or automatic financial/customer approval workflow.
- Owner must confirm actual business contact/address and policy, partner availability and service SLAs.
- Public Git repository contains templates only; no real credential files.
- Reference `docs/GAP_AUDIT_V8.md` for exact source fixes and negative tests.

## Next action for future AI agent

Start from this repo's latest HEAD; read `README.md`, `README_DEPLOYMENT.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_CHECKLIST.md`, and this status. **Do not claim Hostinger deployment, OAuth approval, email delivery or database tests without evidence.** Obtain infrastructure access/credentials securely; do not request secrets in chat. Introduce new coding functionality only against a verified backlog and preserve existing service content/logo.

## 23 Nov 2026 staging continuation: source-only preflight

- [x] Re-ran V8 PHP/JavaScript syntax, workflow, security, local-HTTP and browser smoke suites (source/offline tests passed).
- [x] Added server-side CLI preflight (`secure-setup/maintenance/preflight.php`) with an optional **read-only** DB check; avoids revealing secrets and fails closed on missing live configuration.
- [x] Added GitHub read-only `source-validation.yml` checks, plus `docs/STAGING_ACCEPTANCE.md` with specific test evidence and no-premature-production release gates.
- [ ] Hostinger authenticated integration not available in the current execution environment; **no staging/production Hostinger deployment, DB creation or SMTP delivery took place**. This must be verified separately once a Hostinger session is connected.

- [x] CI first run identified missing `beautifulsoup4` in the clean runner; workflow now installs a pinned dependency in an isolated venv. This is CI-only environment remediation, not live Hostinger acceptance.


## V8.2 source security refinement (pending hosting verification)

- Added optional native PHP owner TOTP (RFC 6238) using an offline/private base32 secret and negative/known-answer tests. **Default OFF** until owner enrollment; partner MFA not yet implemented.
- Removed potentially sensitive free-text job title from customer tracking page; public token shows only minimal status fields.
- Regenerated anonymous CSRF token on successful sign-in.
- Added local/CI tests, private-config preflight checks and deployment instructions. Hostinger staging, real SQL, SMTP, recovery and OAuth approval still pending.

## V8.3 disposable integration test milestone (23 November 2026)

- [x] Added a second GitHub Actions job with a disposable MariaDB 10.11 service, PHP PDO MySQL and a self-signed local TLS test proxy, separate from existing offline source checks.
- [x] Added `tests/integration_mysql_http.py` and a localhost-only, test-only HTTPS simulation router. Tests use **synthetic** contacts, generated owner password hash and a temporary ignored private configuration; this router is NEVER deployed.
- [x] Script covers lead consent/origin/duplicate rate limit, SQL persistence, owner/partner login and session isolation, job creation, blocked pre-approval partner start, recorded owner approval, partner completion, disabled-partner session revocation, customer tracking privacy and expiration.
- [ ] GitHub-hosted integration run must pass on the latest commit before marking these new database/HTTP tests successful. Even when green, it does NOT prove Hostinger staging is deployed, authenticated, backed up or able to deliver email.
- [ ] Hostinger connector/privileged staging access still required for private config, real hosted PDO MySQL, authenticated HTTPS, SMTP delivery, backup/rollback and privacy signoff.


## 23 Nov 2026 V8.4 code-only continuation

- [x] Added transactional lead + optional notification intent persistence and private CLI retry worker (default no SMTP operations without `--run` and private flags).
- [x] Added `notification_outbox` to full SQL schema and a separate additive V8.4 migration.
- [x] Added owner-only delivery counters and manual requeue for failed alerts.
- [x] Extended synthetic MariaDB CI to verify enqueue, dry-run no-write behavior, and owner dashboard summary.
- [ ] **Not deployed:** Hostinger cron installation, real SMTP acceptance/delivery/duplicate handling, production privacy approval and actual backups remain external blockers.

## V8.5 retention fail-safe — source implementation

- [x] CLI retention task is **dry-run by default**; requires explicit `--run` for deletion; invalid arguments fail closed.
- [x] Pending/sending/dead owner alerts and all assigned cases are excluded from lead cleanup.
- [x] Raw DB/PHP exception content no longer printed to cron stderr.
- [x] Disposable MariaDB integration test covers dry-run, explicit delete and protected rows.
- [ ] Real Hostinger retention, restore and scheduled deletion still **not accepted**; staging evidence and privacy review required.
- [x] GitHub Actions run [45197506078](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45197506078) completed successfully: offline source checks and disposable MariaDB integration both passed. This is **not** Hostinger, SMTP or recovery acceptance.

## V8.6 notification failure-path test hardening (source-only)

- [x] Added disposable PHPMailer test double to synthetic MariaDB workflow: six failed attempts, retry backoff/terminal state, authenticated owner requeue and eventual fake success. No network SMTP delivery.
- [x] GitHub CI passed for V8.6 notification failure-path extension on commit `a47ea9121ed91ee254bb755de0fdd922fd7fc05f`; offline checks and disposable MariaDB integration both passed. Run: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45209185309
- [ ] Hostinger staging, real SMTP inbox delivery, backup/restore, privacy authorization and production release remain pending.

## V9.1 growth foundation (source-only)
- Added 14-service registry, review-only task tables and audit, version-checked approve/reject/regenerate request, paused control default, owner-only Telegram webhook source and private CLI draft intake.
- Growth and Telegram flags default OFF. No bot provisioned or webhook registered, no real publishing/outreach, no analytics or AI generator. Verify the new offline and disposable MariaDB CI jobs, then stage separately. See docs/V9_GROWTH_FOUNDATION.md.

## V9.2 optional website analytics (source only)
- V9.1 final CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45354481922.
- Added consent-gated first-party event collector, strict event API, day-scoped tab-session estimates, service interactions, broad traffic channels, click/automation-signal counts, IST saved-lead reports, Telegram query commands and dry-run retention CLI.
- Both analytics flags default OFF. No Hostinger collection, real bot delivery, GA4/Search Console, actual call/mail/message data, campaign-to-lead attribution or social connectors. Validate latest CI and separately perform browser/staging acceptance. See docs/V92_ANALYTICS.md.

## V9.3 evidence-backed opportunity and outreach review (24 Nov 2026)
- Added private CLI import of public/authorized source evidence, all 14 service matching rules, date/URL validation, deterministic score and duplicate control. No network fetching or personal-contact enrichment. Imported evidence requires owner verification.
- Added Telegram opportunity list/detail, qualify/dismiss, pause-gated creation of one linked outreach draft, and draft inventory. V9.1 versioned approval remains review-only; no email or message sender.
- Added additive migration 2026-11-24-v93.sql, independent discovery flag default OFF, preflight and synthetic rules/database tests. Initial CI schema split failed on a SQL comment semicolon; corrected in commit 630af823333bf0f577607b5a65ca7a6a0511aeec. CI run: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45377527089 (status at documentation write: in_progress/pending).
- Still pending: authorized external source adapters, actual contact permissions, message/call/reply records, lead conversion, Telegram test bot, Hostinger and retention approval. See docs/V93_DISCOVERY.md and issue #2. No production change.

## V9.3.1 authorized evidence feed adapter (source only)
- Added private CLI HTTPS JSON transport, explicit source authorization, public IPv4 pinning, size/timeout limits, no redirects, service matching and source-run ledger. Dry-run validates configuration without network; explicit run checks growth pause before each source. /discoveryruns reports bounded import/error counts.
- No actual provider configured or contacted. Synthetic test covers feed contract, destination checks, duplicate/partial/failure accounting and owner report; verify final CI. See docs/V931_SOURCE_ADAPTER.md. Live provider/API acceptance, source retention and real outreach remain pending.

## V9.4 catalog-based local AI creative workflow (source only)
- V9.3.1 CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45378963848.
- Added nine channel formats, four objectives, 14-service catalog-only export, local loopback Ollama structured-output worker, caption/visual/video briefs, versioned result import/history and Telegram /createcontent /content /coverage. Pause and approval gates preserved. No publish connector or rendering.
- Creative flags OFF. Local model and actual bot not connected. Synthetic tests validate input/claims, fake structured outputs and database revisions; final CI needs verification. See docs/V94_CREATIVE.md. No production changes.

## V9.5–V9.6 source milestones (24 Nov 2026)
- V9.4 synthetic creative CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45379962242. Actual model untested.
- V9.5 text-only Telegram business channel scheduling, exact approved revision/destination, private worker, receipt/error states and unknown no-retry. Synthetic CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45380253444. Other social adapters not implemented.
- V9.6 owner daily snapshots and completed-hour error digests, independent notification preferences, private owner bot destination, period deduplication, provider receipts and report history. No live bot delivery or cron. Final CI needs verification.
- See docs/V95_V96_OPERATIONS.md for full activation order and remaining gaps. All new external feature flags default OFF. No production changes.


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


## V10.7H — strict JSON boundaries (source only)
Sixteen reviewed decode calls reject repeated decoded members; existing scope/authorization controls retained. 200 matrix cases and six integrated scope tests authored; development push CI trigger added. V10.7G PR/CI unconfirmed at initial check; new CI and actual hosting pending. See [V10.7H](V107H_STRICT_JSON_BOUNDARIES.md).

V10.7H continuation: discovery/feed, communications, SEO, expert and creative imports and release schema/count references now use strict duplicate rejection as well (16 calls across 15 files). Initial development push CI started; final head CI pending.


## V10.7I — local native video review candidates (source only)
Offline fixed CogVideoX candidate adapter, reviewed 1–12 job/model/font/seed plan, 14-service/9-channel coverage, bounded private motion rendering and review-only index, no transfer or publication. 126 plan cases and synthetic moving-frame codec tests authored. Prior V10.7H CI passed; actual GPU/model, new CI and hosted acceptance pending. See [V10.7I](V107I_NATIVE_VIDEO_CANDIDATES.md).

V10.7I follow-up adds exact read-only candidate re-verification, complete index/bundle and per-job binding, asset integrity and explicit editorial disposition report with publication_authorized=false. No server import or actual review. Final-head CI pending.


## V10.7J — reviewed OAuth rotation and native-video CI correction (source only)
Seven provider replacement credentials can be privately planned and identity-verified without activation. 84 provider cases authored; separate default-OFF flag, HMAC binding, pause/config gates and fail-stop. Corrects V10.7I font fixture without relaxing production checks. New CI pending; actual provider renewal, GPU inference and hosted acceptance pending. See [V10.7J](V107J_OAUTH_ROTATION_REVIEW.md).


## V10.7K — business-facing website completion (source only)
Nine new dedicated service pages; catalog coverage 5/14 to 14/14, static homepage navigation, sitemap/analytics/live SEO coverage 16 public paths, first-load improvements and no-JS inquiry safety. Real performance measurement, content sign-off and deployment pending. See [V10.7K](V107K_SERVICE_WEBSITE.md).

| Milestone | Verified status | Scope |
|---|---|---|
| V10.7J | [All CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45677309456) | Reviewed OAuth rotation, corrected video fixture; actual provider/GPU pending |
| V10.7K | [All three final CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45687062526) | 14/14 dedicated service pages, 16 public routes, static navigation and first-load improvements |


## V10.7L — competitor-informed service conversion (source)
Public editorial benchmark of 10 pages (7 page-text, 3 indexed excerpts), 14 service-specific proposal checklists (56 topics), 14 local discussion brief builders and related navigation. No fabricated claims, no automated sending or new collection. V10.7K final CI passed; V10.7L CI and hosted acceptance pending. See [V10.7L](V107L_COMPETITOR_RESEARCH_AND_CONVERSION.md).

| V10.7L | [All final CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45689093251) | 14/14 service proposal brief builders; competitor editorial benchmark; real staging and conversion measurement pending |


## V10.7M — tender discovery and review (source)
Eight dated public observations (six listed candidates, one unconfirmed, one archived); fixed official source registry, offline 14-service tender shortlist and selected pending research-task bridge. Default OFF, no actual bid/outreach or live import. V10.7L all three CI passed; new CI and automated authorized ingestion pending. See [V10.7M](V107M_TENDER_DISCOVERY.md).

| V10.7M | [All final CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45692821092) | Real public tender observations, deadline-aware offline triage and selected owner research tasks; authorized automated source ingestion pending |

V10.7M owner `/tenders` command and historical observation fixture classification added; source only, final CI pending.


## V10.7N — scheduled tender ingestion (source)
Authorized normalized HTTPS tender feed worker (1–5 sources, 25 notices/source), optional private bearer, reviewed service routing, cadence, atomic pending research task/ledger write and owner queue. V10.7M all CI passed. No actual provider connection or cron installed; final CI pending. See [V10.7N](V107N_SCHEDULED_TENDER_INGESTION.md).

| V10.7N | Source committed; CI pending | Scheduled authorized normalized tender intake to owner research queue; actual provider/cron/staging pending |
