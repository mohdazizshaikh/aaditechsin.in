# V10.6N bounded creative batches and draft integrity

Source-only continuation of V10.6M. Both V10.6M CI runs passed on db3d76688b230811b19ddfa8fbefcf8dd001af1d ([incremental](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494228698), [cumulative](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45494242167)). No schema or flag changes: 36 ordered migrations, 49 tables, 38 pinned source hashes.

## Implemented
- Private creative-batch.php --plan OBJECTIVE [LIMIT] and --apply OBJECTIVE SHA256 [LIMIT] accept an integer 1–126, default 126. Plan reports all missing cells and ordered selected cells; only selected cells are created. Exact v2 digest binds objective, limit and full inventory. Earlier v1 digests must be replanned; changed inventory/limit/objective rejects application. Existing default-OFF/pause/lock/savepoint boundaries preserved.
- Export and import reject invalid stored catalog/channel/objective; result fields require valid UTF-8. Import wraps draft replacement, history insert and audit in one savepoint; a caught insert failure cannot leave a reviewable draft without its result. No automatic approval or publication.
- 126 additional synthetic awareness service/channel workflows, seven 18-cell chunks, 63 rejection paths and 63 revision/approval paths; invalid UTF-8, changed-plan and stale revision denial; test-only forced result collision checks atomic rollback. Other objective isolation and prior 126 case-planning coverage remain. All synthetic rows rolled back. These are test cases, not 126 independent new features or production tasks.

## Acceptance
New CI pending. Source changed without local PHP or disposable MariaDB execution in this chat session; do not count the new cases as passed until the actual CI logs confirm. No real inference, scheduling, publishing, approval, provider requests, production data, merge or deployment. P0 remains cumulative CI and actual authorized isolated staging/restore; P1 local AI/native video, consented provider runtime, GBP write approval, retention policy, authorized discovery scheduling and dashboard acceptance.
