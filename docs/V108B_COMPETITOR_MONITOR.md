# V10.8B — authorized competitor monitoring and historical change review

Baseline V10.8A 880ec145820c8f479bbbc030b8aa21fc26c6c4f6. V10.7Y cumulative CI passed (45716513740). No migration.

## Reviewed monitoring policy
Reuse V10.8A independently authorized exact host/page/robots scope. Supply a private JSON object with version: 1, scope: "authorized-editorial-monitor", enabled: true, reviewed_on and expires_on (ISO UTC dates, maximum 30-day interval), interval_days (integer 1–30), and the exact V10.8A subjects list. Owner must verify each site's terms and permission for repeated automated access, including the selected cadence. No implicit scope or permanent authorization.

Set a private random 32-byte HMAC key in AADITECHS_COMPETITOR_MONITOR_KEY_HEX. Never print or commit it. Use a dedicated canonical 0700 directory outside webroot, one authorized operator/scheduler, and umask 077. Freeze the reviewed policy/key/root during execution. Do not bypass an uncertain attempt by changing the key, root or policy.

`python3 workers/competitor-monitor.py plan < /PRIVATE/policy.json`

Review the exact policy HMAC and deadline. The plan is offline. Separately authorize execution:

`python3 workers/competitor-monitor.py run --root /PRIVATE/monitor --expected REVIEWED_HMAC < /PRIVATE/policy.json`

The operator may configure a private daily cron to evaluate this bounded policy; none is installed. Actual network acquisition is due only after the selected minimum number of UTC calendar days. All existing V10.8A public DNS/TLS, robots hash, scope, body and content restrictions apply every capture. No automatic retry. Within the dedicated policy namespace, a private file lock excludes competing runs. A 0600 attempt marker is created before any capture; the complete capture is saved separately using exclusive creation and file readback. Any incomplete attempt or unsafe history stops future runs until independently reconciled. File fsync is not a guarantee of directory durability; process or storage failure may leave ambiguous state. No automatic journal cleanup or policy rollover. Privately archive and review a completed/failed policy before changing authorization. Maximum 31 history dates; no raw HTML or personal data retained.

## Historical review
`python3 workers/competitor-monitor.py compare --root /PRIVATE/monitor --expected REVIEWED_HMAC < /PRIVATE/policy.json`

Requires two completed captures within the currently approved policy. Compare exact host/service/page mappings; report changed bytes and heading/link count deltas. Same body digest with differing structural metadata is refused. Changed bytes are a review signal only (could be a timestamp, layout, dynamic content, or substantive edit), not proof of new services or business quality. All semantic signals remain unknown until independent V10.8A review. Output does not create tasks or publish. Use existing reviewed editorial comparison and task scope after actual evidence review.

## Verification
168 individual historical changed-page/metadata-refusal scenarios across six hosts and 14 services, maximum 84-page synthetic captures, private file and cadence/expiry/uncertainty fixtures added to CI. No real competitor calls, installed cron, hosted monitoring or production release. CI pending.
