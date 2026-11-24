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
