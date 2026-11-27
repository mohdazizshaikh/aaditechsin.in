# V10.7U — reviewed native video private transfer

Baseline V10.7T f7b6b3f9f9f3966bd6bb82430b16752da91991ba: [CI passed](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45714612643).

## Workflow
Retain the original V10.7S reviewed packet and independent SHA-256. Obtain the server owner editorial approval before delivery; the local worker cannot independently verify that approval, so the V10.7T server import rechecks it. Reuse the reviewed jobs, model, font, seed, plan digest, candidate directory, index, decisions and decision digest.

Use the existing private SFTP configuration schema (enabled, host, port, user, identity_file, known_hosts, remote_inbox). Verify a dedicated authorized private inbox with 0700 permissions, pinned host key, least privilege, adequate space, and support for the OpenSSH hardlink extension. Use a fresh isolated inbox; existing remote final names cause refusal, not overwrite. Freeze source, SSH identity and host-key files throughout.

`python3 workers/native-video-transfer.py --config /PRIVATE/transfer.json --jobs /PRIVATE/jobs.json --model /PRIVATE/model --font /PRIVATE/font.ttf --seed 42 --plan-sha256 CANDIDATE_PLAN --directory /PRIVATE/candidates --index-sha256 REVIEW_INDEX --decisions /PRIVATE/decisions.json --decision-sha256 DECISION_DIGEST --packet /PRIVATE/packet.json --packet-sha256 PACKET_DIGEST`

Default is offline: rebuild the original packet using its validated historical UTC day, rerun full candidate verification, bind reviewed destination and key/known-hosts hashes, and output an exact transfer plan digest. Review and rerun with --send-reviewed-plan TRANSFER_PLAN_DIGEST to authorize the SFTP connection. All selected exact bytes are frozen in a private temporary spool before the first connection (at most 360 MiB of distinct source bytes); local disk must also accommodate one readback pair.

One pair at a time: upload to a random partial name, chmod 600, hardlink to final content-addressed name (fails if existing), remove the temporary link, and download final bytes to verify size and SHA-256. Shared assets already delivered during the same batch are read back without a second upload. No rename fallback, no automatic retry or remote cleanup on failure. Stop on first uncertain result; inspect remote partial/final names and link counts privately before retry. Existing partial/final files may remain. Acknowledged readback is evidence of bytes at that time only, not persistent storage integrity, actual remote ownership or independent approval. Server import requires exact private regular single-link files and performs its own approval and integrity checks.

The packet is not sent automatically; deliver it via separately approved private operator procedure. No publication, new schema, new provider credential, automatic server invocation or actual SFTP session in CI. No real native model inference.

## Verification
Authored real synthetic encoded clip preflight/readback mock, destination/host-key drift refusal, corrupt readback, 24 tamper positions in a 12-pair batch, existing destination refusal, interrupted acknowledgement and shared-asset transfer tests. V10.7U CI pending. Actual Hostinger SFTP hardlink support, inbox permissions, owner approvals, hosted import, actual GPU and provider acceptance remain open.
