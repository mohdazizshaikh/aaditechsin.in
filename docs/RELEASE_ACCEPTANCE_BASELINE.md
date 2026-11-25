# Proposed release acceptance baseline

This is an unweighted inventory of outstanding acceptance gates, not an estimate of total engineering effort. Record evidence links, exact commit/environment and acceptance date when closing each gate. Source tests do not establish hosted or provider acceptance. Do not convert number of commits, lines or tests into completion percentage. Owner may approve weights and scope before a project percentage is calculated.

| ID | Acceptance gate | Status at V10.8E |
|---|---|---|
| R01 | Latest cumulative CI passes both jobs | Pending corrected run |
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
