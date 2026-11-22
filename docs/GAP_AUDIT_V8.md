# V8 gap audit and security remediation (23 November 2026)

**Scope:** inspected the V7 GitHub source (verified baseline SHA `ea67fa26c558d01640139ac5eecd00c5c2a45623`) and the exact 48-file V7 source ZIP. No Hostinger admin session, private production config, actual SQL database or Google API credentials were available for real-world end-to-end testing.

## Implemented and locally tested fixes

| Severity | V7 gap | V8 remediation | Verification |
|---|---|---|---|
| Critical | Partner could move job from `assessment` directly to `in_progress` without customer approval | Server-enforced transition rules in `public_html/api/workflow.php`; partner cannot start work until owner records approval and changes job to `approved` | `tests/workflow_rules.php` + PHP lint |
| High | No mandatory evidence when owner marked a quotation approved | Server requires positive quote and approval evidence method/reference; audit row states owner-entered, not an e-signature | `tests/workflow_rules.php` + source review; staging DB test pending |
| High | Partner password reset/disable left existing sessions valid | Per-partner `session_version` in DB/session; bump on password reset, disable or enable; check every request. Owner admin password hash fingerprint invalidates old owner sessions | Source review + schema migration; staging login test pending |
| Medium | Customer tracking links never expired | Configurable 7–365 day expiry, default 90; stored expiration and query filter, rotation resets TTL; trusted configured HTTPS `public_base_url` replaces request Host to prevent injected links | Workflow tests, negative-path HTTP test; DB test pending |
| Medium | Partner couldn't submit an assessment-only note because same-status row update returned zero | Transactional partner update records note even if status unchanged, with locked assignment row | Source review + rule tests; DB test pending |
| Medium | Disabled partner assignment could be silently dropped by unrelated owner save | Current disabled assignment preserved in owner dropdown and reassign suggested | Source review; staging UI test pending |
| Medium | Data Recovery and IT AMC only had homepage sections | Added unique `/data-recovery/` and `/it-amc/` service pages, internal routing and sitemap links | Static link checks and PHP HTTP smoke |
| Medium | Old deployment guide documented SMTP-only lead acceptance and obsolete directory layout | Rewrote V8 runbook for DB-first inquiry, exact `public_html/` and `secure/` layout, migration, feature flags, tests and rollback | Manual document review |
| Low | Google review code unconditionally relied on `mb_substr` | mbstring-optional fallback | PHP lint; live GBP API remains unavailable |
| Low | Multiple security tests lacked specific workflow assertions | Added workflow, link integrity and HTTP negative-path tests | Local test runs |
| Low | Footer and FAQ still described a private concept or case tracking as unimplemented | Updated descriptive content to distinguish implemented-but-disabled code | Browser smoke checks |

## Important limits and still-open external dependencies

- **NOT deployed / NOT tested on actual Hostinger:** real PHP + PDO MySQL connection, database schema import and migration, real partner/admin session revocation, email delivery, legal privacy review, production cutover and rollback. Test all these in HTTPS staging with synthetic records before enabling flags.
- **No Google Business Profile API access approval** yet. OAuth refresh token and access verification require Google's approval. Keep live reviews sync OFF until authorized.
- **No real LLM, OAuth consent callback, booking/payments or automatic binding quote/contract system**; the existing chat is a deterministic guide, not a live human or AI.
- **No 2FA, comprehensive WAF, SOC monitoring, specialized compliance legal certification, or customer e-signature**. These are outside V8 scope; for business-critical operations consider independent security review.
- **Privacy page is still a DRAFT.** Contact details, partner-data sharing, retention period, service capacity and correct review request link require owner verification.
- Some older code paths and business assumptions depend on Hostinger availability. A passing lint/static/browser check does not prove that production backend configuration works.

## Suggested staging acceptance sequence

1. Export and verify DB backup. Apply new `secure-setup/schema.sql` on fresh staging DB; if existing V7 DB, apply `secure-setup/migrations/2026-11-23-v8.sql` once after backup.
2. Use unique test owner and partner accounts, private `public_base_url=https://your-staging-origin`, and `tracking_ttl_days=90`.
3. Verify partner assessment note is accepted, premature in-progress denied, valid owner approval is audited, and transition to in-progress then works.
4. Reset partner password and disable partner while logged in on another browser; assert old session immediately loses access. Change owner admin password hash and verify old owner session revoked.
5. Create a case, copy one-time tracking link, confirm minimal content and no PII, rotate it and confirm previous link fails. Test expiry in a controlled staging DB.
6. Verify lead consent/storage before SMTP; check notification failures do not erase valid lead and nothing can submit with public feature switches OFF.
7. Run site and mobile smoke, verify original logo, all service routes, privacy handling and real-world navigation; get business owner approval before production deployment.

## Agent handoff rule

Never interpret status in this audit as approval to enable production integrations. For any later code change, inspect latest Git HEAD, run all test suites, compare remote blobs with local hashes, and update `docs/PROJECT_STATUS.md` and deployment checklist. Do not push credentials or user data to this **public** repository.

Additional data-sharing guard: the owner must explicitly check a scope/authorization acknowledgement before first assigning or reassigning a partner; partner portal still must not be used to share private customer details. This acknowledgement does not replace a reviewed privacy notice or lawful data processing basis.
