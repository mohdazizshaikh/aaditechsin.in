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
| Admin access | CSRF rejects, wrong password rejects, login/out works, password change invalidates old session | Redacted test log |
| Partner access | Unauthorized cases and PII inaccessible; disabled partner loses access; cannot start work pre-approval | Redacted test case transitions |
| Quote / approval | Positive approved quote plus minimal external evidence method/reference required; audit event present | Synthetic case ID and event type |
| Customer link | Minimal status, no PII or prices; expiry and rotation invalidate link | Status codes, **not tokens** |
| SMTP | Test message accepted **and delivered** to controlled inbox; DB lead survives SMTP failure | Redacted delivery log |
| Google Reviews | Disabled pending Google API approval | 503 and feature flags OFF |
| Recovery | Backup and restore test, restore timing recorded, production untouched | Timestamp, checksum |

The business owner must review the still-draft `/privacy/` content and authorize appropriate partner-data sharing and customer-data retention before enabling public submissions. Never deploy real customer credentials or live business leads to staging.

## Release gating

Do **not** enable `database.enabled`, `leads.enabled`, `portal.enabled` or public `leadSubmission` until relevant staging tests pass; keep `google.enabled` / `googleReviewsApi` OFF until approved. Do **not** deploy or overwrite production without a verified backup and explicit approval for the exact live target.
