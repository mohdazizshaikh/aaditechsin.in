# V10.7H — strict reviewed JSON boundaries and development CI

Source continuation of V10.7G commit 683220ba78b70b9d8865212c7cf36ea715580f06; PR creation/CI unconfirmed. Latest verified V10.7F CI: 45649879579 and 45649892096. No migration, actual hosting or provider actions.

PHP native json_decode accepts repeated object member names with the last value winning. Shared strictJsonDecode uses native grammar/UTF-8/depth validation, then scans valid JSON structure for duplicate decoded member names in each object, including escaped equivalents and numeric-looking names. Separate objects may reuse names. Existing caller-specific size, shape, authorization, review and transactional controls unchanged. Global upper bound 1 MiB and depth 2–32. No Unicode normalization or authenticity claims. Duplicate errors contain no submitted values.

Integrated seven decode calls: OAuth batch, media quarantine scope, media manifest, communication review, follow-up, competitor review, inbox batch. Other decoders (including discovery and SEO imports) are unchanged and remain pending. Function-only PHP helper has no entry-point behavior.

Tests authored: 200 synthetic matrix cases, six direct scope tests (ordinary and escaped duplicates), Unicode/escape/depth/size/native parity checks. GitHub source-validation now triggers on development branch pushes as well as main and PR events; PRs may run twice. No deployment actions. Verify whether new branch ref triggers CI; if not, use PR or manual workflow dispatch. Actual staging and acceptance pending.
