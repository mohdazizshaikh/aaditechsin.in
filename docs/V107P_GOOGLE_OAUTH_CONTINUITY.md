# V10.7P — automatic Google OAuth refresh for read-only capture

V10.7O all four CI runs passed, final cumulative run 45694707002. V10.7P source changes only; no production or provider access.

## Operational feature

Google supports server-side access token refresh using a previously authorized offline refresh token: https://developers.google.com/identity/protocols/oauth2/web-server . Consent and offline authorization must be obtained separately by the owner; token revocation, expiry, testing-mode grant lifetime and provider policy can interrupt access. This worker does not acquire initial consent, renew a revoked grant or extend the human review deadline.

Two read-only scheduled jobs: Gmail metadata (profile identity and INBOX total/unread counts only) and Search Console (exact property identity and finalized seven-day aggregate). Gmail can operate with expired/absent static access_token when scheduled capture enabled. Each due run exchanges the approved refresh token for a short-lived access token, requires exact granted scope (gmail.metadata or webmasters.readonly), Bearer type and bounded expiry, refuses unexpected refresh-token rotation or missing/broader scope. Token stays in process memory; no token file, database, active configuration mutation or generation change. Existing snapshots remain tied to the reviewed grant generation. Strict duplicate-key JSON at Google refresh and read response boundaries and legacy Gmail candidate read. No email body, messages, recipients, sending or search terms.

## Private configuration and scheduling

- Existing growth/communications/Gmail or SEO/Search Console gates must be enabled independently. New scheduled_capture_enabled flags default OFF.
- Gmail: communications.gmail.renewal.enabled, authorized and scheduled_capture_enabled true, explicit review_expires_utc in UTC, authorized metadata-only client ID/secret/refresh token, exact mailbox, new random generation (32 lowercase hex), revoked=false. Review deadline distinct from access-token expiry. Do not reuse generation from prior manual mode or another grant; change it whenever grant, mailbox or consent scope changes. Old access token not used in scheduled mode. Legacy candidate and manual capture modes refuse scheduled mode; switching modes needs new reviewed generation and stopped workers.
- Search Console: seo.search_console.scheduled_capture_enabled true, existing scConfig authorization, property, grant and review deadline rules. Legacy manual capture refuses while scheduled mode enabled. Use a new generation for a new grant; retain old history according to retention policy.
- Run separately, on approved private staging:
  `php /PRIVATE/secure/maintenance/google-capture.php --check gmail`
  `php /PRIVATE/secure/maintenance/google-capture.php --check search-console`
  `php /PRIVATE/secure/maintenance/google-capture.php --scheduled-run gmail`
  `php /PRIVATE/secure/maintenance/google-capture.php --scheduled-run search-console`
- Example owner-installed cron hourly at different offsets (private PHP path and protected logs). Worker skips capture if same generation/current property window snapshot is less than four hours old; new Search Console window is due immediately. A failed run is not retried inside the process; later cron can retry. No cron installed here. Stop workers before changing private config, rotate generation, revoke access at provider if needed. Do not print credentials in commands or logs.
- Per-provider database advisory lock prevents overlapping scheduled jobs on the same DB server (other deployments on same server share lock names). Growth pause and fresh exact grant are rechecked before exchange, between exchange and reads, and before commit; in-flight requests cannot be cancelled. No config transaction; coordinate edits, and do not run legacy workers concurrently. Token safety window enforced at completion. Existing snapshots can remain visible until stale/disabled after a failed run; they are dated stored evidence, not proof of current connectivity. Review worker exit status and provider authorization.
- Gmail summary in scheduled mode checks current reviewed grant rather than the unused static token. Existing dashboard and owner commands display the stored aggregate; no automatic email handling or response tracking.

## Acceptance
Synthetic grants, exact scope, expiry, revocation, malformed token and rotated refresh token refusal; disposable DB test exercises both captures, pause, grant change, four-hour freshness and stored aggregate reporting. CI pending. No new migrations. Actual authorized Google consent, provider exchange, live capture and hosted cron are pending. Seven social publishing providers still require provider-specific renewal/reauthorization; existing reviewed rotation remains separate. Whole-project 5% not independently measured.
