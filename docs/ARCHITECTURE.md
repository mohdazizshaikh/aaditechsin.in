# Aaditechs Lead Engine & Partner Operations — architecture

## Trust boundaries

1. Public static HTML: homepage, AV pages, guided chat, AV planner; `site-config.js` contains *only public* flags and nonsecret link values.
2. Public lead API `/api/lead.php`: TLS JSON POST, origin and consent validation, strict length validation, filesystem per-client cooldown (hash of IP + private salt), database INSERT. No customer record returned; only opaque short reference. Optional SMTP notification after DB success.
3. Private owner/partner portal `/portal/`: session login, CSRF token per session, password hashes, salted login throttling, owner/partner authorization, no-index/no-store/CSP, admin password-change session invalidation, partner password/disable session revocation. All SQL prepared. Partner role can only see its assigned jobs and never reads `leads` table directly in UI.
4. Customer tracking `/track/?token=<64 hex>`: token is 32 random bytes; database stores SHA-256 and configurable expiration (default 90 days). Public page reveals case code, title, owner-managed status, update date, no personal data or rates. Link possession grants limited access; rotate link on exposure.
5. Private server root sibling `<domain-root>/secure/`: `aaditechs-config.php`, PHPMailer Composer vendor, filesystem rate-lock directories, and CLI-only retention job. Not reachable via web server.

## Database

- `leads`: consent-based customer inquiry details. Do not accept passwords, confidential files or sensitive device data.
- `partners`: active flag, display name, specialty, email login, password hash and session version for revocation. Password is never stored plaintext.
- `jobs`: lead relationship, assigned partner, owner quotation, partner cost, status, SHA-256 tracking token hash and UTC expiry. No automatic financial calculations or promises.
- `job_activity`: role-scoped owner/partner update and audit notes (never public).

## Workflow

Lead intake → admin qualifies → creates case and optionally assigns partner → partner records assessment notes → admin reviews positive quote/cost and obtains documented customer approval outside platform → admin records method/reference and marks approved → partner performs → owner closes → private tracking page reflects status. Partners cannot initiate in-progress work until the approved state. The portal does not infer approval from a form, verify evidence independently, or send actual payment/contract requests.

## State flags

- `assets/site-config.js` `leadSubmission=false` means homepage/chat/AV builder never POST; they clearly show demo mode.
- `secure/aaditechs-config.php` `leads.enabled=false` means backend rejects writes even if public flag is accidentally enabled.
- `portal.enabled=false` blocks private portal and tracking, independent of public flags.
- `google.enabled=false` and `googleReviewsApi=false` retain the genuine-review integration but do not call Google API or display fictitious ratings.

## Security caveats & tradeoffs

- This is a **single-owner lightweight portal**, not a fully audited enterprise IAM or billing system. HTTPS, firewall/CDN rules, two-factor support and security review recommended before handling substantial customer data.
- **Server-side workflow rules** are pure functions in `api/workflow.php`; admin notes and approval evidence are only owner-visible.
- Tracking token links use a trusted configured HTTPS origin, not unvalidated Host headers, and expire by default after 90 days.
- Tracking tokens in URL can appear in web-server logs and browser history; set no-referrer/noindex, instruct customers to keep private, rotate when exposed; use authenticated customer accounts if stronger privacy is required.
- Per-IP file rate limiting is basic abuse prevention, not a WAF or CAPTCHA substitute. Add provider-level abuse control for public launch.
- `privacy/` is **draft**, must be adapted by owner/legal counsel before enabling live leads.
- Lead notifications are best effort; database and owner queue are authoritative. Avoid automated unsolicited marketing.
- Google OAuth approval independent of this app and may be denied; do not bypass it.

## Dependencies

- PHP >= 8.1, PDO MySQL, MySQL/MariaDB, TLS; `mbstring` optional fallback for validation, `openssl` for secure sessions/randomness.
- Composer + PHPMailer for optional SMTP; cURL required for existing Google Reviews endpoint.
- JS browser support: modern evergreen browsers, no external JS framework.

## V9–V10 growth architecture (development snapshot)

Private owner Telegram webhook → reviewed growth tasks and audit ledger → separately enabled analytics/discovery/creative/social/report modules. V9.7 local PC claims catalog-only jobs via authenticated HTTPS lease API; no inbound model port. V9.8 PNG/MP4 generated and retained on private PC; only metadata hashes registered. V9.9 public static guide is separate from private structured expert drafts. V10.0 read-only local SEO audit → private CLI summary → owner Telegram; no page modification or external crawl. All external actions require separately authorized destinations and approved revisions. Database additions are additive through V10.0. See PROJECT_STATUS.md for verification and blockers.

## V10.1 Meta boundary

Separate text-only Meta ledger for Facebook Pages and Threads. Explicit owner schedule, exact approved channel/revision/account, global pause at claim, private CLI identity check and fixed provider HTTPS endpoint. No retries after claim, ambiguous states require manual reconciliation; pause cannot revoke an in-flight send. Instagram and all media uploads remain pending. See V101_META.md.

## V10.2 network boundary

Separate LinkedIn organization, X user-context and GBP account/location text ledger, fixed HTTPS hosts and scoped destination preflight, owner-approved exact revisions, no uncertain retries. Unified /publications counts all three ledgers. Instagram and YouTube require actual authorized binary transfer and upload. See V102_NETWORKS.md.

## V10.2A media boundary

Trusted PC -> manual authenticated private transfer -> CLI SHA256-bound content-addressed MP4 storage -> immutable metadata and exact manifest/revision/visibility/channel review -> explicit YouTube resumable upload -> receipt ledger. No public media URL. Uncertain sends never requeued. Instagram and other media providers remain pending. See V102A_MEDIA_UPLOAD.md.

## V10.2B Instagram boundary

Private hash-verified MP4 -> owner-approved public Reel -> fixed Meta resumable binary endpoint -> processing container ledger -> read-only FINISHED status -> separate explicit publish CLI and receipt. No publicly served binary or provider-supplied upload URL. Recheck Page/linked account at each stage. See V102B_INSTAGRAM.md.

## V10.2C Facebook media boundary

Approved private PNG/MP4 -> hash and profile check -> exclusive text-or-media reservation -> explicit owner approval of kind and Page -> direct multipart exact bytes to fixed Meta host. Local Pillow/FFmpeg/ffprobe verify trusted source assets; server checks hash/basic profile. See V102C_FACEBOOK_MEDIA.md.

## V10.2D network image boundary

Private PNG, exact revision/hash/target, exclusive text-or-image reservation, explicit public owner approval, bounded provider upload/readiness and attachment, no blind retries. See V102D_NETWORK_MEDIA.md.

## V10.2E Meta image boundary

Private JPEG separately reviewed and approved -> expiring bearer capability (digest only, 15 minutes, 20 fetches, exact hash) -> Meta container -> FINISHED check -> revoke token and explicit publish. Anyone holding URL can fetch during window; non-sensitive public marketing only, log redaction mandatory. No permanent public files. See V102E_META_IMAGES.md.

V10.2E uses a separately reviewed 1080x1350 Instagram JPEG derivative while preserving the original 1080x1920 Reel manifest profile. Fetch requires explicit log-redaction verification flag.

## V10.2F private storage boundary

Owner-only existing HTTPS admin session and exact hash-verified referenced media preview. Bounded inventory; protect every historical manifest/ledger reference. Explicit paused private CLI moves old unreferenced bytes to private quarantine or restores them; audit ledger, no deletion. See V102F_MEDIA_STORAGE.md.

## V10.2G OAuth boundary

Private configured credential and non-secret generation/deadline -> optional source-compatible expiry guard (mandatory for live release) -> existing identity preflight -> sanitized latest read-only health record. Local revoke blocks target resolution, not provider consent. No automatic refresh or secrets in DB. See V102G_OAUTH_HEALTH.md.

## V10.2H intermediate media evidence

LinkedIn initialization and uploaded image URN, X uploaded image ID are persisted before final publication. Failure to persist evidence stops send. Read-only reconciliation, no replay or provider token persistence. Video and carousel adapters remain pending. See V102H_MEDIA_RECOVERY.md.

## V10.2H.1 video and reconciliation batch

Private approved MP4 -> exclusive revision -> explicit public authorization -> persistent LinkedIn video URN -> bounded signed multipart PUT -> ordered ETags/finalize -> AVAILABLE owner check -> separate explicit publish. Instagram records Reel container before binary transfer. /mediarecovery includes both. See V102H1_VIDEO_BATCH.md.

## V10.2H.2 X and Threads video boundary

X: private exact MP4, exclusive revision, explicit public approval, persistent media ID, ordered bounded append/finalize, read-only succeeded/expiry check and separate publish. Threads: approved manifest MP4 and exact asset-type approval, same private expiring bearer fetch (15 minutes, 20 requests), FINISHED container and explicit publish; no automatic retry, no permanent public media. Recovery and inventory include both. Carousels need separate ordered multi-asset approval. See V102H2_VIDEO_BATCH.md.

## V10.2H.3 carousel and private inbox boundary

Ordered 2–5 JPEG bundle digest binds asset hashes and byte sizes to approved manifest and account. Distinct owner approval, per-child 15-minute bearer digest/cap, durable child container IDs before next provider write, separate parent assembly and publish claim. Exclusive revision across text/single image/video/carousel. Single bounded video GET ranges after full hash verification; private inbox importer verifies approved PNG/MP4 pair before copying to private storage, no network access or publication. See V102H3_CAROUSEL_TRANSFER.md.

## V10.2I/J transfer and integration health

Explicit local approved manifest digest and exact asset bytes -> private spool -> pinned OpenSSH SFTP batch -> private inbox -> existing approved server-side import. No provider credentials or publication in transport; no cron. Existing owner daily-report preference and separately enabled OAuth alerts summarize configured provider local expiry and stale identity evidence. All publication ledgers contribute historical failed/unknown and current in-flight/processing counts. See V102IJ_TRANSFER_HEALTH.md.

## V10.2K/V10.3 Telegram media and communications boundary

Exact approved private PNG/MP4 -> exclusive Telegram revision -> owner type/manifest/channel approval -> separate business bot identity and channel administrator permission check -> one multipart send -> validated message/channel receipt. No owner bot reuse or unknown replay. Communications: authorized private CLI imports only aggregate channel/completed UTC-day counts, rejects extra fields and conflicting duplicate summaries, no message bodies or identifiers. Existing owner daily reports include previous UTC day only when enabled. See V102K_V103_COMMUNICATIONS.md.

## V10.3A/V10.4 intelligence boundary

Dedicated authorized Gmail metadata credential, exact mailbox profile, fixed INBOX label counts, generation/expiry and pause gates -> single latest sanitized snapshot (no message IDs, headers or bodies). Authorized local discovery JSON uses existing allowlisted feed schema and service matching, transactional batch, duplicate fingerprints and run evidence. Qualified opportunities receive owner-set UTC internal review dates; completion/cancellation audit only, no send. /intelligence and existing daily owner reports aggregate stored evidence. See V103A_V104_INTELLIGENCE.md.

## V10.5 private owner dashboard boundary

/portal/intelligence.php uses existing HTTPS and owner session fingerprint with a 30-minute inactivity gate. Independent default-OFF flag. GET only with fixed 7/30 completed UTC-day periods, no mutation or external requests; server-side escaped aggregate tables and bounded task/due queues. Lead cohorts use creation date and current status, communications show coverage and missing versus zero, workflow counts remain current historical inventories. Disabled modules hidden; no customer details, messages, raw credentials, evidence URLs or drafts. Existing private headers/CSP apply. See V105_OWNER_DASHBOARD.md.

## V10.5A/V10.6A reporting and release verification

Existing owner-only dashboard adds consented website daily trends using completed IST days, separate from UTC inquiry and communications windows; no person-level attribution or raw session IDs. Independent default-OFF aggregate CSV attachment is GET only with same session and private headers, fixed bounded fields and spreadsheet formula escaping. Private communications batch validates all channel/day counts before atomic import; exact duplicates no-op, conflicts roll back. Release contract inventories 28 ordered migrations and 40 required tables; disposable CI replays V9.6 to latest and compares fresh schema metadata. Read-only staging checker inspects required columns and pause without applying changes. See V105A_V106A_RELEASE_READINESS.md.

## V10.6B credentials and retention boundary

Explicit private Gmail renewal candidate uses configured authorized refresh grant, fixed Google token endpoint, exact metadata scope, mailbox identity and INBOX label verification. Candidate stored exclusively in private 0700 directory with 0600 file; never replaces active config or enters DB/logs. Paused growth required before exchange and before save. Communications retention dry-run computes digest over cutoff and locked candidate IDs/snapshot generation; explicit apply rechecks digest, deletes only eligible aggregate rows and stale Gmail snapshot in transaction, retains audit/publication/evidence records. See V106B_CREDENTIAL_RETENTION.md.

## V10.6C signed communications and release gates

Private CLI fetches one authorized per-channel HTTPS aggregate endpoint using a pinned public IPv4, explicit bearer and separate HMAC key. Signed payload binds credential generation, channel, completed UTC day, freshness and counts; strict validation and pause gates before read and commit. Existing immutable aggregate ledger and owner reports reused; no customer details or native provider API connection. Release inventory pins 30 schema/migration/fixture Git blob hashes; read-only DB checker reports unresolved worker/lease, publication and bearer evidence, blocks until reviewed. See V106C_COMMUNICATIONS_RELEASE.md.

## V10.6D reviewed reconciliation and backup integrity

Private paused CLI inspects unknown publication across 12 allowlisted ledgers, hashes the full stored row and intermediate resource evidence, records immutable owner-attested confirmed-present/absent review with independent evidence digest. No original status change, retry, or provider write. Release gate treats only unchanged reviewed unknown rows separately; all in-flight/processing and other blockers remain. Private gzip backup verifier checks SHA256 and bounded decompression, no SQL execution. Gmail candidate local inspection and digest-bound identity revalidation do not activate credentials. See V106D_RECONCILIATION_BACKUP.md.

## V10.6E native usage and synthetic restore

Private explicit GET-only adapters request account-scoped WABA sent/delivered analytics and Twilio inbound SMS/call usage for the previous completed UTC day. Strict account/category/time and local credential gates, latest sanitized snapshot per source, no message or call records requested. Owner reports distinguish provider usage from received/answered/missed aggregates. Disposable CI adds actual MariaDB dump and restore with metadata and synthetic record comparisons, not a production restore. See V106E_NATIVE_USAGE_RESTORE.md.

## V10.6F albums and authenticated inbound boundary

Telegram fixed two-item PNG then MP4 album uses existing approved manifest and exclusive media/text revision ledger, independent owner bundle approval and one multipart sendMediaGroup, validates matching channel/group and two message receipts. WhatsApp inbound endpoint verifies challenge and HMAC-SHA256 over raw payload, exact WABA/phone binding, bounded message IDs/time and stable keyed event digest; persists only digest, account hash and UTC day. No sender/content stored or outbound action; reports distinguish partial webhook observations from inbox/response metrics. See V106F_ALBUMS_INBOUND.md.
