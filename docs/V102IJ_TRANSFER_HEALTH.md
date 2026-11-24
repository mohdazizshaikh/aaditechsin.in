# V10.2I/J private transfer and integration health

Source only; no additional SQL migration. Prior V10.2H.3 migration required. All flags OFF by default. No real host connection, provider check, report delivery, or production changes.

## Explicit local SFTP transfer

Copy workers/media-transfer.example.json into a private 0600 configuration outside the repo. Set enabled=true only for an authorized, isolated SFTP account and destination. Pin server host key in a dedicated private known_hosts file after independent verification (not blind ssh-keyscan trust). Use a dedicated least-privilege SFTP-only key/account, no shell or public webroot access, verify server permissions and quota independently. Private key and known_hosts paths must be absolute and contain only safe path characters. Linux/POSIX OpenSSH client assumed. Source directory 0700 and manifest.json, creative.png, creative.mp4 0600. New renderer outputs have 0600 permissions; review and chmod earlier files privately. Run existing local-media.py verify for actual PNG/MP4 decoding first; transport only verifies exact manifest digest, hashes, size and basic headers. Use the owner-approved manifest digest, not an arbitrary unapproved local manifest. Local script cannot independently verify server approval; server-side import performs that check.

python3 workers/media-transfer.py --config /private/transfer.json --directory /private/render --manifest-sha256 APPROVED_HASH

This validates locally and makes no connection. Add --send to send one pair to the configured inbox. Pinned host key, explicit identity, no proxy/agent/password, batch mode, 180-second timeout. Copies verified bytes to a private temporary spool, uploads each to a randomized partial name, then renames to content-addressed SHA256.png or SHA256.mp4. Remote rename may overwrite an existing same-hash filename depending on server implementation; exact verified bytes and dedicated inbox are required. No atomic pair delivery or remote checksum verification by client; a timeout may leave partial/final files. No automatic retry or remote cleanup. Inspect remote files first. SFTP acknowledgment does not prove imported assets. Run existing private server command php secure/maintenance/media-inbox.php --import TASK REV MANIFEST_SHA256 to verify approval, both hashes and pause before storage import. No automatic publication or scheduled transfer. Actual Hostinger SFTP/permissions acceptance pending.

OpenSSH documentation: https://man.openbsd.org/sftp and https://man.openbsd.org/ssh_config .

## OAuth attention and integration reporting

oauth_lifecycle.alerts_enabled defaults false. /connectionalerts reports enabled providers needing attention: expiry within seven days, expired/revoked/unverified/not-enforced state, missing/stale/failed identity evidence. Disabled providers omitted. Daily owner report includes attention summary only when enabled, uses existing owner-only delivery, daily preference and period deduplication; no separate cron or delivery channel. Existing daily scheduling must be configured and verified; alerts are daily snapshots, not immediate expiry notices. No token or raw provider payload is exposed. Local deadline is not provider introspection; identity evidence is not permission to publish. Existing manual rotation procedure remains unchanged; automatic consent/refresh/revocation and encrypted vault pending.

Current /errors and daily report now summarize all eleven publication ledgers: historical failed/unknown, current in-flight and current processing/parent-ready counts; not time-scoped incidents or visibility. Existing hourly completed-period error counts remain limited and unchanged. Reconcile uncertain posts manually, never retry blindly. Synthetic mock transport and disposable DB tests wired into CI; actual SSH, scheduler, owner notifications and provider acceptance pending.

Next priorities: CI verification, remaining mixed-media/Telegram/GBP adapters, actual OAuth renewal/alerts, communications monitoring, opportunity discovery, central dashboard and staging/release.
