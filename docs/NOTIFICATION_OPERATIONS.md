# Lead Notification Operations — V8.4

**Implementation scope:** code only. No Hostinger staging or real SMTP results.

The lead API commits both the lead and a single owner notification intent atomically **only when `mail.enabled=true`**. The request never connects to SMTP. When mail is disabled the lead is stored without an alert row, deliberately avoiding future unexpected backfill.

## Prerequisites

1. Staging-only private MySQL and restricted PHP settings, and the V8.4 additive migration.
2. Store all credentials outside the public webroot in private `secure/aaditechs-config.php`.
3. Install `phpmailer/phpmailer` via Composer in the **private** `secure/vendor/` directory.
4. Before scheduling cron, confirm the private worker is physically outside `public_html`, callable only over CLI, and SMTP test inbox is under the owner's control.
5. Keep `.gitignore` exclusions intact. No plaintext credentials in any logs or Git.

## Commands

```bash
php /path/to/domain-root/secure/maintenance/notification-worker.php --dry-run
php /path/to/domain-root/secure/maintenance/notification-worker.php --run
```

The read-only command prints aggregate `pending`, `sending`, `sent`, and `dead` counts; the worker requires `mail.enabled` and `leads.enabled` for `--run`. After acceptance schedule the latter in Hostinger Cron Jobs every 5–15 minutes. Verify with a controlled inbox.

The worker claims up to 20 alerts per run with an atomic compare-and-swap update and a 15-minute lease. Temporary failures are queued with exponential-ish backoff. After six attempts, failed alerts are visible to the owner as `dead`, with an explicit requeue action following diagnosis. **At-least-once** semantics mean rare duplicate alerts are possible after crashes. Do not use email delivery as the sole record; MySQL is authoritative.

## Acceptance evidence

- Successful lead generates exactly one outbox record and remains in MySQL if SMTP is unavailable.
- Public POST does not block on SMTP.
- A successful private cron run changes a pending alert to sent; separate test mailbox confirms delivery.
- A failing SMTP endpoint increments attempts and transitions to pending then dead after six failures; owner sees counter and can requeue.
- Negative case: when the migration is omitted and mail enabled, lead insert must roll back rather than silently omit notification.
- Backup and restore tested for both leads and queue, not just static HTML.

The synthetic CI test exercises six simulated mail failures, bounded retries to `dead`, an owner-only requeue, and a fake successful send using a disposable PHPMailer test double. **No external SMTP connection or real mail delivery occurs in CI.** This validates database state transitions only; live SMTP acceptance/delivery remains an explicit staging release gate.

The private retention prune deliberately **does not delete leads with pending, sending or dead notifications**. Owner must resolve failures before an unrelated retention job removes their diagnostic record.
