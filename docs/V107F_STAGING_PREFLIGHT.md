# V10.7F — complete staging flag and schema preflight

Source-only continuation of V10.7E (#76/#77 CI passed: 45638451736, 45638461550). No migrations, provider calls or production changes.

The private preflight now enumerates 113 boolean leaves of the current example, including nested provider authorizations, revocations and redaction attestations. Missing/nonboolean values block readiness rather than being silently displayed OFF. Upgrade old private configuration by reviewing the new example, never overwrite existing secrets. Explicit dependency checks cover 76 child flags; these are configuration consistency checks, not authentication or provider-specific validation. Actual runtime gates remain authoritative.

For initial staging only, before enabling any database/provider feature, run `php secure/maintenance/preflight.php --staging-baseline https://EXACT-STAGING-HOST` with the exact lowercase HTTPS origin configured. All known booleans must explicitly be false, including authorization/revocation attestations; this is a new all-off setup gate, not an instruction to clear a revoked production credential. Reject known production origins, IP literals, paths, credentials, ports and malformed hosts. This is syntactic and operator-confirmed isolation, not DNS or account verification. No database/network call. Normal preflight remains for progressively enabled staging, with explicit boolean and dependency checks. Existing checks for credentials, private paths, runtime and SMTP remain. --check-db now loads the colocated release-contract.json and checks required columns across all 49 tables rather than only 18 old tables. This checks presence, not extra objects, types, indexes, worker stop, or backups. Copy preflight-rules.php and release-contract.json alongside preflight.php in private maintenance; keep all private files out of webroot. Existing release-check.php remains separate.

Tests authored: exact template boolean coverage, 339 flag cases, 98 table/column absence cases, dependency and invalid-origin checks. CI pending. Actual isolated Hostinger, backup/restore, provider approval and application acceptance pending.


## V10.7G — exact deployment integrity (source only)
Commit-derived bounded SHA-256 inventory of all tracked public files/private maintenance scripts, private staged-copy parity, missing/drift/extra/unsafe checks, preflight config path and permission gate, 102 synthetic file cases. Prior V10.7F CI passed; new CI and actual hosting pending. See [V10.7G](V107G_DEPLOYMENT_INTEGRITY.md).
