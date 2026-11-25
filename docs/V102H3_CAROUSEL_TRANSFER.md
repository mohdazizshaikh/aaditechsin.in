# V10.2H.3 Carousel, private inbox and media delivery batch

Source only, new flags OFF. Apply 2026-11-24-v102h3.sql after V10.2H.2. Official API references: https://developers.facebook.com/docs/graph-api/reference/shadow-ig-user/media/ and https://www.postman.com/meta/threads/request/34203612-b0861a47-db0e-4940-a692-2304669603b3 .

## Carousel

Enable media_upload.threads_carousel_enabled or instagram_carousel_enabled with existing image/fetch/log-redaction and provider/approval gates only after staging verification. Source supports 2–5 distinct RGB JPEGs per ordered bundle, same existing provider image profile, no mixed/video slides. Original approved PNG must also be verified when staging; derivative JPEGs require independent human review, not claimed to be generated from the original. The bundle digest hashes canonical ordered hashes and byte counts. Caption, provider, account and current approved manifest are bound separately. Private CLI: php secure/maintenance/carousel-worker.php --stage PROVIDER TASK REV MANIFEST_SHA256 /private/original.png /private/slide1.jpg /private/slide2.jpg. Owner /carousels, /carousel ID, /approvecarousel ID BUNDLE_SHA256 PROVIDER ACCOUNT_ID, /cancelcarousel ID. Inspect all ordered slides before public approval. --children PROVIDER ID creates each child and persists its validated ID before next write; --parent PROVIDER ID checks every child FINISHED, revokes fetch tokens and creates ordered parent; --publish PROVIDER ID independently checks parent FINISHED and publishes once. --status PROVIDER ID reports local state only. Uncertain/crashed claims require manual reconciliation; no automatic retry or provider deletion. Receipt is not visibility.

Each child has its own 256-bit bearer, only digest in DB, 15-minute expiry and 20-fetch cap; anyone holding URL can fetch approved bytes. Use non-sensitive marketing content only; require real hosting/CDN log redaction, no cache and quotas. Carousel references protected from quarantine. /publications and /mediarecovery include parent ledger, /carousel ID lists recorded child IDs. Exclusive provider revision across carousel/text/image/video.

## Private inbox import

Enable media_upload.inbox_import_enabled, set private_inbox to separate 0700 private directory outside webroot and outside private_root. Transfer files into inbox using existing authorized SFTP workflow, with names SHA256.png and SHA256.mp4. php secure/maintenance/media-inbox.php --import TASK REV MANIFEST_SHA256 validates current approved task/manifest, pause, exact PNG hash/dimensions and MP4 hash/basic header (not full decode) before storing both files in private_root and recording audit. No publication staged, no credentials or remote connection, source files retained; partial filesystem copy can remain if later write or DB commit fails, inspect before retry. Remote SFTP client automation remains pending.

## Video delivery

Existing media-fetch.php supports a single explicit byte range for approved video GET, full hash verified before slicing; 206 for valid range, 416 for invalid/unsupported range, no suffix/multipart/image/HEAD range. All authorized requests consume fetch quota even invalid range. No streaming-memory optimization; maximum 20 MiB asset, verify Hostinger memory limits and actual provider Range behavior. Existing full GET/HEAD remains supported.

Synthetic mock/DB coverage added for ordered digest, duplicate rejection, exact JPEG fetch, child evidence, parent state, no retry, range policy and inbox import. CI pending at writing. Actual OAuth/format/provider/HTTP acceptance, remote transfer automation, mixed-media carousel, credential renewal/alerts, communication monitoring and remaining modules pending. No real posts, no production changes.
