# V10.6S reviewed local media batches

Source-only continuation of V10.6R. Incremental CI passed: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560435935. Cumulative run https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45560440719 was in progress at initial inspection. No schema or feature flag changes.

## Local operator workflow
Export each current approved text revision separately from the existing private media-worker CLI. On trusted local machine assemble exact JSON object {"version":1,"jobs":[...]} containing 1–12 exported job objects (one revision per task). Keep files private. 
- `python3 workers/local-media.py batch-plan --jobs PRIVATE_JOBS.json` validates all jobs without rendering or network access, returns canonical content digest and count.
- Review actual input job content, renderer, model, font and disk capacity independently. Digest binds the jobs and order, not renderer/font/model binary identity.
- `python3 workers/local-media.py batch-render --jobs PRIVATE_JOBS.json --plan-sha256 REVIEWED_HASH --output PRIVATE_ROOT --font TRUSTED_FONT.ttf --renderer template` renders sequentially in a new private directory, verifies every bundle and writes a private batch-index.json after success. For local-diffusion supply independently vetted installed model directory and offline isolation. On caught failure the new batch directory is removed; abrupt OS termination can leave incomplete files, so require a complete index and re-verify before individual review/registration.
- No transfer, upload, registration, owner approval or publishing performed by batch mode. Each bundle still requires manual actual content review, current text revision and exact manifest hash verification via existing server workflow.

## Validation and security
Exact current service titles, channel profile and byte limits, common unsupported claim/contact checks, strict JSON duplicate key rejection. Local bundle verification enforces canonical private directory, regular single-link bounded files, exact manifest schema/hash/renderer, PNG integrity and silent single-stream H.264 yuv420p MP4 dimensions/duration/decode. POSIX permission checks; Windows requires independent restrictive ACL verification. Trusted owner-only filesystem assumed; not a sandbox against concurrent malicious local writers. No new media types: MP4 remains four-second image motion slide, not native generative video. Actual diffusion/native video, model quality, license, disk/hardware and provider acceptance pending.

## Tests
126 synthetic service/channel input cases; real synthetic two-bundle template render plus prior single-image/fake-diffusion tests; digest mismatch, duplicate task, rollback, strict manifest, permissions and path rejection. New CI pending. Tests are not separate completed production features. No merge, hosting access or deployment.
