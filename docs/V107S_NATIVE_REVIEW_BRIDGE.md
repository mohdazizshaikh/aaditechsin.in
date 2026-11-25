# V10.7S — native video editorial review handoff

Baseline V10.7R b2b6d24aaa8fe290cf0dd32ef62137e98beb426f: [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45699431744), including the synthetic database/media archive restoration.

## Workflow

1. Render and independently verify local V10.7I candidates, review actual frames, and obtain the exact editorial decision report SHA-256 from the existing review command. Freeze the private inputs throughout. Do not forward rejected clips.
2. Run the existing native-video-candidate.py review command with mode replaced by handoff and add --decision-sha256 REVIEWED_DECISION_SHA256. Keep all other jobs/model/font/seed/plan/directory/index/decisions arguments. Redirect stdout to a new private packet file (umask 077). Record its actual SHA-256 independently. This reruns candidate verification and filters only forwarded items; no media files are copied or manifest produced.
3. On approved private isolated staging, enable growth, creative, media and the independent media.native_review_enabled flag (default false). Growth must be resumed. Use the current UTC day packet. Run:
   - `php secure/maintenance/native-review.php --plan PACKET_SHA256 < /PRIVATE/packet.json`
   - Review exact source IDs, revisions, hashes, existing tasks, and plan digest.
   - `php secure/maintenance/native-review.php --apply PACKET_SHA256 REVIEWED_PLAN_SHA256 < /PRIVATE/packet.json`
4. The server verifies canonical strict JSON, 1–12 unique task IDs, exact review record digest, dimensions, current approved text revision, canonical job digest and service. Plan is rolled back; apply repeats the plan under the growth-control lock and creates only pending system tasks with private audit entries. Existing exact title/service review tasks are preserved. A stale plan refuses. Keep operator-created review task titles intact; renaming/deleting a task may defeat this title-based deduplication. Existing owner task review commands can inspect these tasks; generic task approval does not approve a media manifest.

## Security and limits

The packet hashes bind integrity, not a cryptographic signature or proof of real AI inference, human review, or source authenticity. The server does not have candidate bytes or model files and cannot independently prove the decision report or index came from the local runtime. Trust the authorized operator and separately record reviewed digests; inspect the actual files privately. The UTC day is an export date, not proof of review time. No new schema, transfer, publication adapter, provider access, approved media manifest or native renderer schema entry. No actual model/GPU run, hosting or publication. Existing template/local-diffusion renderer labels remain unchanged. A later separately reviewed native binary import path is still required.

## Verification

126 service/channel scope cases, 1–12 bounds, malformed/duplicate/stale digest rejection, a 12-item disposable DB scenario including pause, replay, source drift and zero new media manifests, and Python-to-PHP canonical handoff of a real synthetic moving clip. Tests authored and CI pending. No 5% whole-project completion claim.
