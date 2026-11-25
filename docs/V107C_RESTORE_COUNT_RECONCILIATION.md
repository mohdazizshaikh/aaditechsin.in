# V10.7C — Private restore row-count reconciliation

Source continuation of V10.7B, whose incremental #70 and cumulative #71 CI passed (45634148688, 45634157251). No schema/migration changes (36 migrations, 49 tables, 38 pinned hashes). No actual backup, restore, production access or deployment.

## Operator procedure
On isolated source and restored target use the exact same schema contract and comparable MariaDB version. Stop all workers, disable public writes and keep growth paused. Independently verify backup SHA-256 and actual provenance. The source must be frozen throughout dump and reference capture; a later live source is not a valid reference. Never use the restored target as its own reference. Keep all files private (umask 077). Capture before restoring or allowing any new source writes, using the digest of the actual dump:

`php secure/maintenance/release-check.php --capture-counts BACKUP_SHA256 > /PRIVATE/counts.json`

Record reference file SHA-256 independently. On the isolated restored instance:

`php secure/maintenance/release-check.php --compare-counts BACKUP_SHA256 REFERENCE_SHA256 < /PRIVATE/counts.json`

Both modes validate source contract hashes, paused and clear listed worker state, exact structural metadata, and count all 49 expected InnoDB tables in a read-only repeatable-read transaction. The reference stores canonical decimal row counts only, contract/metadata digest and operator-supplied backup digest. No row contents, credentials or database name emitted. Counts are private business metadata. Compare emits table match/drift, never values, and exits nonzero on mismatch or invalid reference. Canonical bytes reject duplicate JSON members. Backup digest binds the claimed artifact identity, not proof that the reference came from that backup. Count queries may be expensive; run only on isolated quiescent database under operator monitoring. No automatic restore, writes, DDL, grant inspection, row-content equality, independently proven worker stop, or production approval. Matching counts do not prove completeness; validate representative records, relations, permissions, storage/media and application workflows separately.

Backup verifier now opens with nonblocking flags before checking regular file type, rejects FIFO and detects compressed length growth or short reads during digest pass. Existing gzip, private file and digest checks retained.

## Verification
98 synthetic table-specific count drift/missing inventory cases, independent digest and decimal boundary tests, disposable DB count drift when fixture clear, pause/worker refusal and FIFO fixture authored. CI pending. No actual hosted backup or restoration performed. P0 isolated staging and actual restore acceptance remain open.


## V10.7D — keyed restore content fingerprints (source only)
Private bounded read-only content fingerprint comparison across 49 tables; keyed per-table HMAC with primary-key order, binary field framing, metadata/backup binding and canonical reference. 98 table cases and same-count mutation regression authored. Prior V10.7C CI passed; new CI and actual isolated restore pending. See [V10.7D](V107D_RESTORE_CONTENT_FINGERPRINTS.md).
