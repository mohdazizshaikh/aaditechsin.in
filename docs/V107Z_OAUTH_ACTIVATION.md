# V10.7Z — reviewed staging OAuth activation

Baseline V10.7Y 9aed70ffabe11a385521f14fb6cfebcec2b8989a. No schema change. Source implementation only.

## Explicit prerequisites
Use an independently verified isolated staging deployment with separate database, provider grants and HTTPS origin. Origin comparison rejects the known production names but cannot prove actual isolation, DNS ownership or absence of aliases. Stop all external workers, freeze owner configuration edits, pause growth, verify backup/recovery and confirm provider consent/scopes. Enable existing lifecycle/rotation/orchestrator and applicable candidate flags, plus oauth_lifecycle.activation_enabled (default false).

The trusted private config must already be in the exact canonical static format produced by PHP: "<?php" newline "declare(strict_types=1);" newline "return " followed by var_export(config,true), semicolon and newline. This is intentionally stricter than the example template. Dynamic/config-with-comments installations are refused; do not automatically flatten arbitrary PHP configuration. Owner must separately review and convert a copy on isolated staging, or use manual rotation. Private config must be a canonical 0600 single-link file in a 0700 secure directory. Backup directory must be canonical 0700, outside webroot and separate from candidate directories. Treat backups as full live secrets.

## Operation
Use an exact version-1 provider scope: {"version":1,"providers":["youtube","x"]}. Independently confirm each candidate's consent, scope, expiry and provenance. Use fresh private AADITECHS_OAUTH_ROTATION_HMAC_KEY_HEX (32 random bytes in hex, never in Git/chat/logs).

`php /PRIVATE/secure/maintenance/oauth-activation.php --plan https://staging.example.invalid /PRIVATE/backups < /PRIVATE/scope.json`

Plan is offline: exact provider order, current static config bytes, candidate bytes, complete grant, staging origin, backup directory and for LinkedIn/X matching attempt/response journals are bound by HMAC. No secrets printed. Review the resulting provider rows and digest.

`php /PRIVATE/secure/maintenance/oauth-activation.php --apply https://staging.example.invalid /PRIVATE/backups REVIEWED_HMAC < /PRIVATE/scope.json`

Apply takes orchestrator and all provider renewal locks, checks pause and exact plan repeatedly, probes only the reviewed candidate identity through provider-specific read-only adapters, then holds the pause row while backing up the exact old config bytes to a random 0600 file and preparing a same-directory 0600 temporary file. Revalidate config/plan, replace via rename and read back exact replacement bytes. Updates only selected access token/policy generation/deadline and, for LinkedIn/X, the matched returned refresh token, conservative expiry and new grant generation. Threads renewal is suspended (enabled/authorized false) until independently verified new issue time and actual provider expiry are recorded. Candidate and journal files are retained. No provider publication or unpause. No automatic rollback: a refresh token may have been consumed, so restoring old credentials without independent verification is unsafe. A failed apply after rename can leave new configuration active; inspect privately, retain backup and reconcile before resuming. File/DB/remote operations are not distributed atomic transactions; stop concurrent writers, reload PHP workers/opcache as appropriate and verify configuration/identity before controlled resumption. Backup is recovery evidence, not guaranteed usable provider authorization.

## Verification
127 nonempty provider subsets, selected/unselected credential comparison, mismatched rotated response, unsafe file, static config and isolated-origin gates authored. Disposable DB tests exercise actual fixture config replacement, exact backup and journal retention, pause, wrong identity and stale replay; no actual provider exchange. CI pending. Hosted activation, production release and automatic unattended activation are not performed.
