# V10.8F — consolidated release readiness and split staging package

V10.8E source baseline ddeee0a1eb85bd4e7367ae07aa5f0ffe71a24517 is 280 commits ahead of main 7582be3c9beafa7335c1ced9bc9c6369b4360daf, zero behind, as checked before this batch. V10.8B CI passed. V10.8C/D offline checks failed at missing BeautifulSoup; both disposable DB jobs passed. V10.8E corrected the interpreter, but offline failed earlier at exact flag-order assertion; disposable DB passed. V10.8F restores lexicographic flag order and adds exact-day competitor row validation. New CI must pass.

## Build split packages from a reviewed Git commit

Run from a trusted local checkout, with Python 3 and Git. Create a canonical 0700 private output parent outside the actual webroot. Independently review the exact commit SHA and Git source (including contents of public files). Use:

`python3 workers/staging-package.py pack --commit EXACT_40_HEX_COMMIT --directory /PRIVATE/packages`

Creates a new private directory named by the commit, never overwrites or retries an interrupted output. Uses Git object content rather than dirty working files. Four mode-0600 artifacts: public.zip (public_html only, regular 0644 entries), private-maintenance.zip (secure/maintenance PHP/JSON only, regular 0600 entries), deployment-reference.json (existing exact deployment manifest), package-receipt.json (commit, exact reference digest, two archive digests, counts and sizes). Deterministic stored ZIP members, fixed timestamps, bounded file inventory and bytes, verified on creation. Local disk and memory must accommodate the bounded archives. Record receipt digest independently in private review notes.

`python3 workers/staging-package.py verify --directory /PRIVATE/packages/EXACT_40_HEX_COMMIT --receipt-sha256 REVIEWED_SHA256`

Validates private file modes, no symlinks or hardlinks, exact directory inventory, receipt digest, reference and archive membership/content. No extraction, upload, network, deletion, or automatic repair. Receipt is a checksum, not a signature or proof of provenance; only use independently reviewed trusted package. Keep writers stopped throughout. Never upload the private archive, receipt, or reference to public_html. Paths are relative to the isolated domain root; do not extract a package into an existing production tree.

## Hosted handoff sequence (not executed)
1. Confirm a separately authorized Hostinger staging domain, isolated database/user, root, HTTPS, private directory, and stop workers. No production settings or credentials reused.
2. Review consolidated draft PR and exact cumulative CI run. Build package from approved commit only. Independently verify archive receipt.
3. Transfer public files and private maintenance files to their respective staging paths. Private config example, schema/migrations, dependencies/vendor, backup/media, local workers and credentials are intentionally NOT packaged. Follow existing README_DEPLOYMENT and staging checklist for those separately; do not put them in webroot.
4. Verify exact deployed file inventory using V10.7G, including permissions, extra files and missing/drift checks. Perform all-off baseline, isolated database/media restore, fingerprints, schema parity, pause and worker-state checks using prior tools.
5. Run owner/partner/anonymous HTTP acceptance, provider and runtime acceptance in staged increments. No production deployment until independently reviewed and approved. Record exact evidence in release acceptance baseline.

200 synthetic source-drift/missing-member scenarios, deterministic archive and private receipt tests, plus 28 stale capture positions authored. No real staging package produced for upload, hosted file copied, actual backup restored, or Hostinger account accessed. Consolidated review PR against main does not merge or deploy. No new schema migration.


## 27 November 2026 — cumulative CI acceptance and release artifact workflow

Both V10.8F jobs passed [run 45819867093](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45819867093) at exact commit 6e4258075909bf9cc42616c91028b36ff614e1cd: offline and disposable MariaDB integration, 151 explicit flags and 551 preflight matrix cases, 200 split-package regressions, 28 stale capture row refusals, 168 competitor analysis cases, 84 reviewed recommendations and 84 missing-peer refusals, 168 tender ranking and 98 lead service/date cases. No production or hosted acceptance asserted.

The consolidated branch now adds a release-package CI job, after both required jobs succeed on a push/manual run of this exact release branch. It builds the actual commit's four source-only package files, runs a disposable file-tree rehearsal, verifies every deployed file byte/size and permission rules against the manifest, then publishes only those four files as a short-retention GitHub Actions artifact. No deployment, hosting access, secrets, database or actual media. Pull-request synthetic merge commits and other branches do not publish packages. The downloadable artifact is repository source, not a private secret store; retain repository access controls and do not expose maintenance scripts publicly. GitHub artifact downloads may not preserve file mode: recreate a canonical 0700 private directory named by exact commit and set the four files to 0600, independently compare the receipt digest in the successful run log, then run the existing verify command. A successful disposable rehearsal is not actual Hostinger staging. R01 is verified at the stated V10.8F baseline; rerun required for every new head. R02–R20 remain open until independent acceptance. No merge or production release authorized.
