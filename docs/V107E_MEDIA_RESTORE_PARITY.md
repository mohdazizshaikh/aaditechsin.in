# V10.7E — private media restore parity

Source continuation of V10.7D (#74/#75 CI passed: 45637881776, 45637889102). No schema changes (36 migrations, 49 tables, 38 pinned hashes), real backup, restoration or deployment.

## Operator workflow
Use isolated frozen source and restored target, same release, existing storage inventory gate, paused growth and clear listed worker state. Stop all writers and disable public writes independently. Verify database and media archive provenance and digests separately. Use same private randomly generated HMAC key environment as V10.7D and the independently recorded media archive SHA-256, not a guessed digest. Keep reference private with umask 077 and record its digest independently.

`php secure/maintenance/media-storage.php --capture-restore MEDIA_BACKUP_SHA256 > /PRIVATE/media-reference.json`

`php secure/maintenance/media-storage.php --compare-restore MEDIA_BACKUP_SHA256 REFERENCE_SHA256 < /PRIVATE/media-reference.json`

Exact active and optional quarantine flat directory inventory, no unknown entries, aliases or duplicate names across locations; private regular asset file checks, content-addressed SHA-256 and existing format validation. Cap 1000 total assets, 256 MiB of bytes, 1000 distinct references per source and 10000 total across 15 fixed DB projections; fails closed on excess rather than sampling. All actual asset names and sizes contribute to keyed zone fingerprints, and reference names/location contribute to keyed per-source fingerprints. No asset paths, names, content or per-file hashes exported. Missing or quarantined referenced assets reported separately and compare exits nonzero even when matching the reference. A source may legitimately have not-yet-transferred assets; reconcile before release rather than treating parity as availability. No archive extraction, file writes, automatic repairs, provider calls or deletion. Reference binds operator-supplied archive digest, not archive provenance. File observation is not an atomic filesystem snapshot; stop all workers and other writers throughout capture. No full codec validation or external publication acceptance.

Staging preflight now displays the three newer review batch flags. Tests authored: 51 zone/source-specific drift, inventory and shape cases, budget, unknown entries, availability and disposable reference completeness if fixture clear. New CI pending. Actual isolated restore, media transfer, approvals and application acceptance remain outstanding.
