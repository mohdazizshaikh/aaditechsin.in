# Proposed release acceptance baseline

This is an unweighted inventory of outstanding acceptance gates, not an estimate of total engineering effort. Record evidence links, exact commit/environment and acceptance date when closing each gate. Source tests do not establish hosted or provider acceptance. Do not convert number of commits, lines or tests into completion percentage. Owner may approve weights and scope before a project percentage is calculated.

| ID | Acceptance gate | Status at V10.8E |
|---|---|---|
| R01 | Latest cumulative CI passes both jobs | Verified at 6e425807 (run 45819867093); new head revalidation pending |
| R02 | Consolidated branch reviewed and merged | Pending |
| R03 | Independently isolated Hostinger staging and HTTPS | Pending |
| R04 | Actual staging database restore and content fingerprints | Pending |
| R05 | Actual private media restore and referenced-asset checks | Pending |
| R06 | Hosted privacy, auth, CSRF and owner/partner boundaries | Pending |
| R07 | All 14 service routes and real inquiry intake acceptance | Pending hosted |
| R08 | Lead qualification and response/follow-up review acceptance | Source added, pending CI/hosting |
| R09 | Actual owner bot and SMTP delivery/reconciliation | Pending |
| R10 | Gmail metadata and consented communication provider acceptance | Pending |
| R11 | WhatsApp/SMS/call provider and retention acceptance | Pending |
| R12 | Seven-provider OAuth grants and credential renewal/activation | Source implemented, live pending |
| R13 | Social publication/media provider acceptance | Pending live |
| R14 | Actual local AI content/expert inference | Pending runtime |
| R15 | Actual native GPU video and secure transfer/import | Pending runtime |
| R16 | Actual Search Console/SEO authorized capture | Pending runtime |
| R17 | Authorized competitor capture, monitoring and semantic model | Pending runtime |
| R18 | Authorized tender feed, amendments and owner queues | Pending runtime |
| R19 | Hosted scheduling, monitoring, failure recovery and owner handoff | Pending |
| R20 | Reviewed production deployment, smoke and rollback readiness | Pending |

No gate is marked closed without its own required evidence. Equal weighting is not assumed; this inventory covers final acceptance, not all historical development or the entirety of effort.


V10.8F update: R01 still open; V10.8E disposable DB passed, offline preflight flag-order failure corrected in source. R02 remains open pending cumulative CI and reviewed consolidation. R03 package preparation automated at source level; actual staging target, transfer and HTTPS isolation unverified. No gate closed by packaging alone. See [release readiness](V108F_RELEASE_READINESS.md).


27 November 2026: R01 passed at exact V10.8F commit 6e4258075909bf9cc42616c91028b36ff614e1cd, [CI 45819867093](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45819867093). Subsequent artifact workflow changes require a new successful run; do not carry CI acceptance across changed commits. R02 review/merge and R03 actual isolated hosting remain open. A disposable reconstruction is not hosted restoration. See [release readiness](V108F_RELEASE_READINESS.md).


27 November follow-up: both validation jobs passed on [PR merge run 45920763895](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45920763895), candidate 588bfba; artifact job skipped, inventory empty. Same-repo PR exact-tree package trigger corrected, current head pending. R01 evidence remains exact-commit/checkout specific; R02 review and merge remain open (no reviews submitted); R03 actual staging remains open.
