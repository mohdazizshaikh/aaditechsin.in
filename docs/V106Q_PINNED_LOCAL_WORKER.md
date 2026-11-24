# V10.6Q bounded local AI worker

Source continuation of V10.6P. Prior incremental and cumulative CI passed on c1ca5a2ca1bd3e914c702195ed831268b31689a5: https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45498426102 and https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45498432910. No schema or flags changed (36 migrations, 49 tables, 38 pinned hashes).

## Operator workflow
Existing single-job invocation remains compatible. For bounded operation explicitly configure a trusted HTTPS staging endpoint and independent protected worker token, an independently verified installed local model tag and digest, and restrict local Ollama network egress. Example:
`python3 workers/creative-poll.py --endpoint https://YOUR-STAGING-HOST/api/creative-transport.php --model EXACT_LOCAL_TAG --model-digest VERIFIED_64_HEX_SHA256 --limit 10`
Limit 1–20; more than one job requires a digest. The inventory is checked before any claim and before/after each generation; exact model response tag required in pinned mode. Inventory is self-reported, not attestation. Only one lease at a time; empty queue, acknowledged generation failure, invalid acknowledgement or uncertain transport stops the invocation. Existing identical completion retry remains; never retry a claim automatically. Existing single-job mode without a digest is legacy compatibility, not pinned assurance. No automatic scheduling, model downloads, actual provider calls, publication or customer input. Owner must still configure non-overlapping local scheduling and independently verify runtime/offline installation.

## Integrity
Creative input must match exactly one entry in the locally checked-in public service catalog. Strict JSON rejects duplicate object keys in model output and CLI input/transport. Creative and expert workers reject explicit mismatched model responses; pinned creative additionally requires model identity field. Existing model/byte/claim and review restrictions remain. No arbitrary job context or credentials transmitted to Ollama.

## Verification
56 service/objective positive synthetic cases and 56 corresponding tamper denials; 28 additional expert planning/escalation cases; bounded empty/fail/limit/uncertain acknowledgement and pinned-model mismatch/rotation checks. These are tests, not separately shipped production tasks. New CI pending. Actual local Ollama, native video, diffusion, hardware/codec performance, scheduling, provider acceptance and isolated hosted restore still outstanding. No merge/deploy.
