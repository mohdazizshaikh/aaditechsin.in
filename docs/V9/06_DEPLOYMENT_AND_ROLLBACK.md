# V9 deployment and rollback — Hostinger, simple first

**Status:** preparation/runbook for future V9 code. Current source branch adds documentation only. **Do not run a V9 migration or enable any V9 feature from these planning docs.** Refer to [V8 deployment runbook](../../README_DEPLOYMENT.md) for the existing tested-source layout and pending external approvals.

## 1. Dependencies and capability gates

| Dependency | Where | Required for first slice? | Verify before assuming |
|---|---|---|---|
| Existing Hostinger shared hosting + SSL | Owner account for `aaditechs.in` | Yes | Real hPanel plan, staging domain, DNS/cert and protected backup |
| PHP 8.1+ with PDO MySQL/OpenSSL | Hostinger | Yes | Version, extensions and resource limits on actual account |
| MySQL/MariaDB with InnoDB | Hostinger | Yes | Real DB/user, migrations, indexes, transactions, backup+restore |
| Private folder outside `public_html` | Hostinger | Yes | Verify config and vendor not HTTP-accessible |
| SMTP transactional provider | Server config | Strongly recommended | Private auth, recipients, accept vs deliver test |
| Cron / scheduled PHP CLI | Hostinger | For auto follow-ups/reports | Actual cron frequency/process/timeout, CLI PHP path, locking |
| Search Console / GA4 | Google account | No for basic leads | Owner property verification and consent policy |
| Ollama inference | Separate secured local/VPS service | **No** (optional) | 24×7 availability, TLS/auth, latency and privacy review |
| Google Business Profile API | Google | **No** | Eligibility then official approval and OAuth; keep OFF until approved |
| Meta/Google Ads APIs | Separate developer/business accounts | **No** | Authorization, policies, billing and owner spend caps |

## 2. Deployment topology

```text
Hostinger domain root/
  public_html/          existing V8 UI, PHP API, portal, service routes
  secure/               private server config, vendor, cron scripts, logs
  (MySQL managed database)

Optional later:
  Private AI host        Ollama-compatible model behind authenticated TLS gateway

GitHub: feature/v9-growth-engine → reviewed PR → main → controlled staging upload
```

Do not create a separate worker VM or new hosting plan by default. Single-site PHP cron must use bounded batches; if a later real workload exceeds shared-host process limits, evaluate a small worker separately with evidence.

## 3. Secrets: where and how to configure

1. Start with the existing `secure-setup/aaditechs-config.php.example`, but place the **real** copy only at `<domain-root>/secure/aaditechs-config.php` outside `public_html`. Review and restrict permissions per actual Hostinger filesystem support. Never overwrite a real config with a template during deployment.
2. Configure `database.*`, `portal.admin_email`, `portal.admin_password_hash`, `rate_limit_salt`, `public_base_url`, `tracking_ttl_days`, `mail.*`, and eventually `google.*` per existing V8 runbook. Keep disabled during staging first.
3. Proposed **future** V9 keys (NOT PRESENT YET IN CODE):

```php
// Example keys ONLY for a future reviewed extension of private secure config.
'v9' => [
  'enabled' => false,
  'ai_enabled' => false,
  'followups_enabled' => false,
  'opportunity_fetch_enabled' => false,
  'content_publish_enabled' => false,
  'ads_write_enabled' => false,
  'ai' => [
    'base_url' => 'REPLACE_WITH_PRIVATE_AUTHENTICATED_HTTPS_ENDPOINT',
    'token' => 'REPLACE_WITH_PRIVATE_PROVIDER_TOKEN',
    'model' => 'REPLACE_WITH_APPROVED_MODEL_ID',
    'timeout_seconds' => 8,
    'daily_request_limit' => 100,
  ],
  'cron_batch_limit' => 25,
],
```

4. Add each actual config key only in the same implementation PR as its tests; validate values server-side. Do not commit live values, host private config or copy any config outside trusted infrastructure. Environment-specific staging/prod keys must differ.
5. For private inference, do **not** expose `localhost:11434` to the public internet or rely on the owner's laptop being permanently online. If private remote inference is not practical, keep AI OFF and retain deterministic guided chat.

## 4. Staging-first migration plan

1. **Inventory** live hosting paths, SSL, DB, DNS, mail, backups, business details and current feature flags without changing them.
2. **Backup** website files + database; verify restore on a separate staging target and record checksum/time/owner approval. Confirm emails/DNS are unaffected.
3. **Create isolated staging** HTTPS site with no-index, unique credentials, separate DB and fake test records. Do not point the live `aaditechs.in` domain at a half-built branch.
4. **Install V8** using existing runbook, confirm schema vs migrations (fresh schema OR V7→V8 migration) and run role/lead/tracking/SMTP tests with synthetic data. This is a V9 prerequisite.
5. **Implement V9 slice-by-slice** via versioned forward migrations; verify schema and one-step rollback/feature disable; no mixed production and staging databases.
6. **Deploy static/public front end**, validate browser and phone/email fallback. Only then enable one server V9 switch + matching public feature at a time.
7. **Review release report:** code SHA, migration IDs, DB backup ID, test evidence, errors, pending approvals and exact rollback instructions. Obtain explicit owner consent before replacing live files.
8. **Post-release monitoring:** check accepted DB leads (not just emails), rate-limit/abuse, outbox queue, session anomalies, response time, 4xx/5xx, source/campaign attribution and real conversions.

## 5. Cron schedule and operation — proposal, not current task

Use one CLI-only `maintenance/v9-runner.php` invoked at a moderate interval supported by the actual account (for pilot, every 5–15 minutes if available) and perform bounded work:

1. acquire lock and log run ID;
2. recover expired *leases* safely, cap retries;
3. process `owner_review` reminders/consent-valid outbox items in batches of ≤25;
4. update dead-letter/errors; release lock, record duration, alert owner on backlog.

Use individual enable flags for follow-ups/content/discovery. Do not run AI calls inside an unrestricted public HTTP request or perform uncontrolled long-running cron. Do not schedule a cleanup job that deletes customer records without reviewed retention policy.

## 6. Release gates by module

| Gate | Required evidence | Default after git merge |
|---|---|---|
| V8 lead storage | Real staging PDO insert/reference + consent + privacy approval | OFF |
| V8 partner portal | Staging login/CSRF/role/approval/revocation/link expiry | OFF |
| V9 AI chat | Offline fallback + schema validation + abuse tests + private model endpoint verified | OFF |
| V9 follow-up | Opt-in, unsubscribe, SMTP delivery, retry and suppression tests | OFF |
| V9 SEO content | Owner-approved copy/photos, proper mobile links, Search Console property | No scheduled publication |
| V9 opportunity watch | Allowed source list, no outbound outreach, owner review UI | OFF |
| V9 Ads | Verified account/API/policy, owner fixed cap and one-time approval, dry-run | OFF |
| V9 finance | Collections/cost reconciliation with owner and permission tests | Private only |

## 7. Rollback strategy: safety before speed

- **Code/UI rollback:** turn V9 feature flags OFF, restore last working V8 files/commit (not a force push), clear relevant cache, verify service pages/chat/lead fallback and HTTPS.
- **Database:** **prefer forward-compatible additive migrations** and leave new empty V9 tables for investigation rather than blindly dropping them. A rollback from migrated DB requires reviewed, tested down-migration or restore from verified snapshot after assessing post-backup leads.
- **Queued messages:** immediately pause external sends and cancel unapproved queued campaigns; do not re-send blindly after restart. Audit provider status for uncertain attempts.
- **Ads/social:** pause via authorized account directly if needed, not just app flag; retain owner/account access and track actual spend. A server rollback cannot reverse already delivered ads/messages.
- **Incidents:** update owner status and capture evidence privately; preserve customer records and comply with retention/breach responsibilities.

## 8. Go/no-go checklist

- [ ] Actual Hostinger staging verified and *existing production backed up/restorable*.
- [ ] All enabled modules pass [test matrix](07_TEST_AND_ACCEPTANCE.md) with synthetic data and owner-signed evidence.
- [ ] Current public privacy/terms/business details accurately describe data/partner use.
- [ ] Provider accounts and consent terms verified; no secrets or live data in Git or frontend.
- [ ] Owner approves exact domain, files, migration, time window, partner notifications and rollback operator.
- [ ] Google/API-dependent or paid features remain disabled if their approvals or access are not proven.

**This documentation branch must not be auto-deployed to live Hostinger.**
