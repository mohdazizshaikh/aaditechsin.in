# V10.6U reviewed server-side media import

Source continuation of V10.6T; both CI runs passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568204750 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568209111. No schema changes (36 migrations, 49 tables, 38 pinned hashes). New media_upload.inbox_batch_enabled defaults false, independently requires existing growth/creative/media/upload/inbox gates.

## Operator workflow
After local review, individual server manifest registration/approval and authorized SFTP delivery, privately prepare JSON {"version":1,"items":[{"task_id":123,"revision":2,"manifest_sha256":"EXACT_64_HEX"}]} with 1–12 unique task scopes. Use independently verified private sibling inbox and storage roots. With growth resumed and authorized shell:
- `php secure/maintenance/media-inbox.php --batch-plan < /PRIVATE/scopes.json` validates current approved text/media, catalog channel dimensions, source hashes and size/header/profile checks, existing destination integrity; returns count and digest. No files or audit rows written.
- Review exact scope and digest. `php secure/maintenance/media-inbox.php --batch-apply REVIEWED_SHA256 < /PRIVATE/scopes.json` repeats preflight under growth-control lock, verifies digest bound to order, manifest asset hashes, sizes and private root paths, then copies content-addressed files and records individual audit events. One pair buffered at a time. Source retained. Exact destination files reused; repeat apply may add audit events. No publication staged.
- Caught SQL/audit failure rolls back batch audit rows via savepoint, but filesystem writes cannot roll back with SQL; partial verified files may remain. Inspect destination and source, recheck approvals and generate fresh plan before retry. Do not remove referenced files blindly. No remote atomicity or automated cleanup. Local full codec verification and actual human review still required; server checks header/profile/hash only. Trusted private filesystem, permissions and stopped competing maintenance workers required. Existing single import preserved with destination preflight.

## Verification
84 synthetic request matrix cases (12 valid sizes, 72 invalid position/mutation cases), plus bounds/duplicate checks and disposable MariaDB approval, disabled, pause, digest, source tamper, exact file reuse and retention scenarios. New CI pending. No actual hosting, files, SFTP, external provider or production deployment. Test cases are not independent production tasks.

## Subsequent CI evidence

Both V10.6U CI runs passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568784213 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568789959. Actual hosted imports remain pending.
