# V10.7D — Private keyed restore content fingerprints

Source continuation of V10.7C (#72/#73 both CI passed: 45636945746, 45636959590). No new migration, backup, restoration or deployment. All 49 contract tables covered.

Use exact release code, comparable MariaDB/PHP versions, identical connection character settings, UTC session, frozen isolated source and isolated restored target. Stop workers, block public writes, pause growth, verify actual backup digest/provenance separately. Capture source before any new writes. Generate a fresh high-entropy 32-byte secret per restore exercise; supply as lowercase hex via the private process environment AADITECHS_RESTORE_HMAC_KEY_HEX on both systems, never as an argument, committed config, logs or shared chat. Environment may be readable by privileged processes; use an isolated operator account. Unset it after use. Keep reference private (umask 077); record its SHA-256 independently.

`php secure/maintenance/release-check.php --capture-content BACKUP_SHA256 > /PRIVATE/content.json`

`php secure/maintenance/release-check.php --compare-content BACKUP_SHA256 REFERENCE_SHA256 < /PRIVATE/content.json`

Read-only repeatable-read CLI. Exact contract and structural metadata; only validated column and primary-key identifiers. Fixed sorted column order, primary-key row order, binary SQL projections, length-delimited fields distinguish NULL, empty, boundaries and binary data. Per-table HMAC-SHA256 binds backup digest, metadata digest, table and layout. Streaming unbuffered cursor with 1,000,000 total rows, 512 MiB framed data and 4 MiB single-field caps; fails closed on excess, never silently samples. Cursor cleanup may still consume remaining server results; monitor load and stop manually if required. Reference includes per-table count and keyed digest only; no individual row hashes or customer fields exported. Canonical exact reference bytes, table inventory and digest checked; compare emits match/drift only. Wrong key produces drift, not recoverable data. All matching fingerprints give strong evidence of equality of the selected SQL binary projections under identical runtime semantics, not proof of backup provenance, independent worker stop, external media, auto-increment state, grants, routines or production readiness. Do not use source database as its own restored target. No automated SQL restore, repair, retries or deployment. Retain keys separately and securely until comparison then destroy according to approved policy.

98 table-specific fingerprint drift/inventory cases, key/table binding, field framing, caps, deterministic ordering, pause/worker refusal and disposable actual same-count mutation checks authored. Disposable content capture runs only when fixture is clear; otherwise gate refusal is tested. CI pending. Actual isolated hosted restore and representative application workflow acceptance still pending.


## V10.7E — private media restore parity (source only)
Full bounded active/quarantine asset verification and keyed parity across 15 reference sources, missing-reference readiness gate, no raw asset names exported, three staging flag visibility updates. 51 manifest matrix cases authored. Prior V10.7D CI passed; actual restoration and new CI pending. See [V10.7E](V107E_MEDIA_RESTORE_PARITY.md).
