# V10.7R — disposable database and media restore rehearsal

Baseline: V10.7Q eabbb84c9738e21adfa918f35192bd27f73d02f1, [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45697990011).

## Implemented test coverage
The existing migration replay now creates a synthetic MariaDB dump and a separate gzip-compressed tar archive of generated PNG/MP4 fixtures. Two active media files are referenced by a synthetic manifest; a third PNG is an unreferenced quarantine fixture. The CI-only PHP helper exercises the real release metadata, row-count, keyed content and media restore capture functions in a read-only repeatable-read transaction. It binds the actual SHA-256 values of both archives and a freshly generated in-process test key.

The test restores the database using the MariaDB client after the existing guarded reset of the disposable database, and reconstructs a fresh private media directory using only the known synthetic tar members. It requires matching complete metadata, counts and keyed fingerprints across all 49 contract tables, two media zones and 15 reference sources. It also checks that a same-count SQL mutation changes only the expected table fingerprint and a missing referenced MP4 is observed as missing; reverting both must reproduce the full reference. This is 98 table comparisons and 17 media zone/reference comparisons, not 115 separate backlog tasks. Existing migration/fresh-schema parity remains intact.

## Safety and limits
- Only the fixed loopback CI database, explicit synthetic environment and empty-start guard; no production/staging DB or credentials. Test helper is not deployable tooling. Media paths are restricted to fresh private temporary CI directories.
- Test archive extraction is allowlisted to its own generated members, not a general archive restore utility. No customer data, external sources, credentials, actual native AI inference or provider calls.
- CI pending for this head; actual Hostinger restore, independent staging database and media archive provenance, worker-stop evidence, measured recovery time, application acceptance and production authorization remain open. A disposable round trip in the same database container is not an isolated hosted target.
