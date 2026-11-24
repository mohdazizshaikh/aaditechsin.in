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
