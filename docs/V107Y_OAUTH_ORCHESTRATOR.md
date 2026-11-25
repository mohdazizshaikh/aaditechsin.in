# V10.7Y — unified OAuth renewal orchestration

Baseline V10.7X a93872eef4c3ff7c70fc7d66ef1cc79f18ac40db. V10.7W cumulative CI passed (45715822221). No schema change.

## Operator workflow
Seven provider families share a private status inventory. It reports current local policy/deadline, due/not-due, unverified candidate presence, reconciliation required, unsafe private file or invalid grant/directory. File presence is not validation; no file contents, credential values, IDs, paths, network calls or active changes are returned. Even revoked grants expose existing journals if the directory remains configured. Use:
`php /PRIVATE/secure/maintenance/renewal-orchestrator.php --status`

For a reviewed batch enable existing growth, OAuth lifecycle, rotation review and individual provider acquisition flags, plus new oauth_lifecycle.orchestrator_enabled (default false). Stop competing workers, pause growth, freeze config and secure storage. Create private exact version-1 scope JSON: {"version":1,"providers":["youtube","threads","linkedin"]}. Order is significant; 1–7 distinct providers, no implicit selection. Use fresh private random 32-byte HMAC key AADITECHS_OAUTH_ROTATION_HMAC_KEY_HEX (not in shell history, Git or logs).
`php /PRIVATE/secure/maintenance/renewal-orchestrator.php --plan < /PRIVATE/scope.json`
`php /PRIVATE/secure/maintenance/renewal-orchestrator.php --run REVIEWED_HMAC < /PRIVATE/scope.json`
Plan is offline; binds exact reviewed grants, provider order, local due decision, database and private file states. Reject entire scope if existing candidate/journal/unsafe directory or blocked grant. Run validates plan, takes global advisory lock, then dispatches only due providers using the existing per-provider locks and grant/identity gates. Check every remaining reviewed grant before each dispatch; stop on first uncertain result, drift, busy or non-candidate result. Successful prior candidate files remain; never retry the old scope blindly. Review status and make a new plan for remaining providers only after independent reconciliation. Due thresholds remain provider-specific (Meta seven days, Google/LinkedIn/X fifteen minutes). Status can be inspected without enabling orchestration. No unattended activation or scheduler installation. Actual provider scopes, refresh-token eligibility and grant expiry must be confirmed separately. Candidate files remain secrets.

## Boundaries
Provider-specific journals and existing rotation verification remain unchanged. There is no active config mutation, automatic reconciliation, grant acquisition, provider publishing or hosted deployment. A filesystem write and database lock are not a distributed transaction. Status reports metadata, not live authorization. Operators must independently reconcile rotated refresh tokens, verify candidate and explicitly authorize active credential replacement. Do not install unsupervised cron during live operations.

## Verification
127 nonempty provider subsets, private inventory, unsafe paths, redaction, due/deadline drift and disposable database full-batch/stop gates authored. CI pending. No live provider access. Seven-provider source coverage is not production acceptance.
