# V9.7 authenticated creative worker transport (source only)

The local AI PC initiates outbound HTTPS to the trusted staging host; Ollama stays on loopback. Only V9.4 catalog jobs and validated creative drafts are transferred. No customer data, prospects, publishing permissions or DB credentials cross this interface. This is not an expert chatbot or media renderer.

## Staging setup
1. Back up staging; apply 2026-11-24-v97.sql after V9.6. Fresh installs include the table in schema.sql.
2. Generate an independent random 32-byte token on a trusted computer (e.g. Python secrets.token_hex(32)). Store it only in the local worker's protected environment/credential store; calculate SHA-256 of its lowercase hexadecimal string and configure only the digest in private aaditechs-config.php. Never put the token in a URL, shell history, public Git, logs or Telegram. Keep growth, creative and creative_transport OFF until staging acceptance. Use a dedicated token, not the owner or publishing bot secret.
3. Verify HTTPS, host identity, proxy/header forwarding, request size limits and server-side rate limits for this endpoint; never log the custom token header. Rotate the token by replacing the digest and the PC secret. Disable creative_transport to revoke all API access. Configure Ollama local-only with cloud disabled and keep port 11434 inaccessible from the internet.
4. Resume growth and create a content request via the owner bot. With the secret already supplied via protected environment, run:
   
   python3 workers/creative-poll.py --endpoint https://YOUR-STAGING-HOST/api/creative-transport.php --model YOUR_LOCAL_MODEL

One job per invocation. Schedule locally with a non-overlap lock and a modest polling interval only after acceptance. No inbound port or remote database access. Keep the endpoint pinned to the trusted host in the local configuration.

## Recovery and review
Claims have a 10-minute lease and at most three attempts per revision. A failed or expired lease is eligible for a new attempt; exhausted revisions require owner regeneration or the existing private manual import. Lost claim response waits for expiry. Result acknowledgement can be retried with the same token and identical validated content; stale, mismatched, expired or conflicting results cannot overwrite drafts. The global pause blocks claims and completion. Existing manual import can supersede a lease; it never permits overwrite. Completed drafts are pending owner review, not published. The ledger retains hashes and attempt counts; review retention policy before production.

## Acceptance still required
Real Hostinger TLS and header forwarding, rate limiting, credential rotation/revocation, concurrent PC runs, actual local Ollama inference, interruption/restart, manual recovery, migration backup/restore and scheduling. No activation or production deployment by source CI.
