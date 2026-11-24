# V9.1 Growth foundation — source only, no external dispatch

The 14-service registry mirrors the website catalog and is parity-tested. V8.6 remains intact. New additive tables: growth_tasks, growth_audit, growth_control and growth_telegram_updates. Fresh installations use schema.sql; existing staging databases apply 2026-11-24-v91.sql after backup. No migration is executed by this commit.

## Owner workflow
Private CLI growth-owner.php accepts --create or --replace with JSON on stdin, or --today for a database report. Create fields: service_id, task_type (research/seo/content/outreach/social/system), title, draft. Replacement fields: id (integer), revision (integer), draft. No secrets or customer personal data in drafts. No AI generation or external execution adapter yet.
Telegram /approvals and /task ID display review data. /approve ID REV, /reject ID REV, /regenerate ID REV require the exact pending revision. Regenerate increments revision and requires a new draft; it does not invoke an AI. /pause and /resume persist a control flag, initially paused. No external dispatcher exists; future dispatchers must check pause, exact approved revision, permission and channel policies before each action.
/today and /health report total saved leads and stored task state counts, not daily attribution or full health. Visitors, emails/messages, actual calls, reach, SEO, social and errors are NOT measured yet; report says unconnected instead of claiming zero.

## Telegram staging
Official API: https://core.telegram.org/bots/api. Configure an owner-only private bot via BotFather and setWebhook to the HTTPS staging /api/telegram-owner.php with a random secret_token of 32+ allowed characters. Store real webhook secret and numeric owner user/private chat IDs in private config only. Restrict allowed_updates to message. Keep growth.enabled and growth.telegram.enabled false until migration, HTTPS and authorization tests pass. Never paste bot tokens in chat, commit them, or expose them in logs. Register the webhook privately; no registration command runs in this repo. Endpoint rejects stale updates, other users, groups and bad secrets; transactionally deduplicates update_id. It returns a direct webhook sendMessage instruction, which does not confirm Telegram delivery. No scheduled alerts, callbacks or role delegation yet. Test on a dedicated test bot, not a production account. Review Telegram update retention and lifecycle before switching bots (update IDs are bot-specific).

## Remaining scope
1. Privacy-reviewed first-party event collection and reports (page views, estimated visitors, bot signals, source attribution, click vs confirmed calls).
2. Verified opportunity-source adapters and evidence, opt-in/referral intake, authorized email/message/telephony records.
3. Local Ollama expert service, content drafts, image/video workers, versioned approval and content coverage.
4. Official channel OAuth and authorized publishing/reply adapters, delivery receipts, retry/outbox, notification preferences and error dashboard.
5. Owner portal UI, proactive Telegram summaries, granular roles, credential rotation, staging/backup/restore.
No prospect scraping, unsolicited messaging, actual publication, or fake metrics. No claims of Hostinger deployment.
