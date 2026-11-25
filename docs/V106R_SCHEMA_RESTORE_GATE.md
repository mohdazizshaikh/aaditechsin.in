# V10.6R read-only schema and backup release gates

Source continuation of V10.6Q, both CI runs passed on 0b344e0c31831c8b0518df4047b03538b1c93d17: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45499069195 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45499077052. No migration or flag changes (36 ordered migrations, 49 tables, 38 pinned schema/fixture/migration hashes).

## Reference and comparison
Existing --source-only and --check-db remain unchanged. Stop workers, pause growth and reconcile unresolved evidence on both isolated instances. Use a trusted fresh schema instance of the exact release and a comparable MariaDB version; inspect reference provenance independently. From a private shell with umask 077:
`php secure/maintenance/release-check.php --capture-schema > /PRIVATE/reference.json`
Record SHA-256 independently. This emits bounded structural metadata only (not business rows, DB name or credentials). It is not a signed reference or backup. Keep private; custom defaults could contain sensitive values. Never use a restored target as its own reference.
On the isolated restored target:
`php secure/maintenance/release-check.php --compare-schema EXPECTED_SHA256 < /PRIVATE/reference.json`
Compares fixed ordered table/engine/collation/type, column definitions/defaults/nullability/extra/charset, indexes and FK definitions/rules. Exact contract inventory required; unexpected tables/columns, non-InnoDB tables, cross-schema FK, paused-control failure or unresolved work block. Outputs only per-category match/drift, never metadata values. Reference bytes and exact release contract bound; 1 MiB and 10,000 rows per category caps. Different server versions can yield harmless metadata differences requiring human review; no automatic alteration or migration. Does not check grants, triggers/check constraints, stored routines, data completeness, actual worker-stop status or live provider acceptance.

## Backup integrity
Private gzip verifier additionally refuses intermediate symlink aliases and hardlinks, invalid expansion limits, empty streams and changed file identity after reading. Use canonical absolute private path and independently recorded digest. Still never executes SQL; actual restore on separately authorized isolated hosting and independent data checks required. Existing disposable CI dump/restore rehearsal remains.

## Tests and limitations
49 independent table-level synthetic column-drift cases and three other metadata categories, valid round trip, digest tamper and contract rejection, private gzip alias/hardlink/limit/empty tests. Tests are not independent production tasks. New CI pending. No actual Hostinger credentials, backup, restore, DDL, merge or deployment used. P0 actual isolated hosting and owner-approved restoration remain open; P1 native AI video, GBP media, OAuth lifecycle, provider and retention acceptance remain pending.

## Subsequent CI evidence

V10.6R incremental CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560435935. Cumulative run https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560440719 was in progress at V10.6S initial inspection. Actual hosted restore remains pending.


## V10.7C — restore row-count reconciliation (source only)
Private read-only count capture/compare across 49 contract tables; exact metadata/backup binding, canonical reference, table-specific match/drift, pause and worker gates, backup FIFO/size safeguards. 98 table-specific scenarios and disposable DB count checks authored. Prior V10.7B CI passed; new CI and actual isolated restore pending. See [V10.7C](V107C_RESTORE_COUNT_RECONCILIATION.md).
