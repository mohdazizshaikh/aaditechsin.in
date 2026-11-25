# V10.6T reviewed private media transfer

Source continuation of V10.6S, both CI runs passed on 7abd15168147c7d426c1d711d25049ba53fdfbac: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560708189 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560712922. V10.6R both CI runs also passed. No migration or new server flag (36 migrations, 49 tables, 38 pinned source hashes).

## Explicit operator workflow
After V10.6S local batch rendering, inspect every actual bundle, register manifests privately, obtain owner approval for each exact current revision on the server, and independently record SHA256 of completed batch-index.json. If any item not approved do not use whole-batch transfer; use individually approved single-bundle mode. Index digest is a local integrity check, not server approval proof or signature. Independently verify dedicated SFTP config, host key, account and private inbox. 
`python3 workers/media-transfer.py --config /PRIVATE/transfer.json --batch-directory /PRIVATE/media-batch-DIR --batch-index-sha256 REVIEWED_INDEX_SHA256`
Default validates locally only: exact index, one revision per task, 1–12 safe child directory names, manifest hash, matching IDs/revision/renderer, profile, private canonical paths, bounded assets and full local PNG/MP4 decode. No connection unless explicit --send. With --send all bundles preflight before any connection, then send sequentially through existing pinned SFTP sender (exact bytes revalidated and spooled per bundle). Stop on first uncertain/failing delivery; report only prior acknowledged count. No automatic retry, rollback or remote cleanup; inspect partial and final files first. SFTP acknowledgment does not prove remote checksums, receipt or server import. Explicit individual approved server imports still required; never publish automatically. Existing single-bundle CLI remains compatible with stricter manifest and canonical paths, but single mode still requires operator to run full local-media verify separately. Trusted private local filesystem assumed; no remote authenticity proof beyond existing host key pin. Whole-batch send may leave partial progress.

## Verification
18 positive channel/renderer profile fixtures and 108 negative manifest mutations (126 cases), mocked two-bundle transfer and interruption, scope and index rejection, no network calls when final bundle invalid. Header-only transfer fixtures are not full codec tests; actual local media codec suite separate. New CI pending. No real SFTP connection, production deployment, model inference or native AI video. Actual hosted transfer/import and provider authorization remain pending.

## Subsequent CI evidence

Both V10.6T CI runs passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568204750 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45568209111. Actual SFTP remains pending. V10.6U adds separately gated reviewed server batch import.
