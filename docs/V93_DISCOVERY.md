# V9.3 evidence-backed discovery and outreach review — source only

No network fetching, directory scraping, prospect emails, automatic calls or publishing are implemented. Intake is a private CLI adapter for authorized research exports or human-reviewed public evidence, not an autonomous lead crawler. No personal contact information is required. Review each source's authorization, terms, purpose and data-retention policy before import.

## Intake
Use private `growth-owner.php --import-opportunity` with one JSON object on stdin. Required string fields: source_type (tender/company_announcement/authorized_directory/referral/manual_research), source_url (clean public HTTPS, no query/fragment/credentials/port), organization, location_label, evidence (short non-personal excerpt or factual paraphrase), observed_on (UTC date within 180 days), signal_type (explicit_request/expansion/upgrade/general_fit), service_id (one of the fourteen registry IDs). No passwords or personal contact details. Heuristic filters are not a complete privacy scanner; sanitize before import. Source links are not fetched or verified by code.

Matching rules cover all fourteen service IDs and require at least one matching phrase in evidence. Score = signal weight + capped keyword count + recency. Score is a prioritization heuristic, not buying likelihood or factual verification. Deduplication uses normalized organization + evidence URL + service. Reimport does not replace existing evidence; concurrent duplicate may require retry. Import one record per service, using the same evidence when appropriate. All new records pending review. Actual confirmed enquiry is a separate V8 lead record.

## Owner commands
/opportunities lists pending top ten; /opportunity ID shows evidence, source and score. /qualify ID or /dismiss ID records human review; qualified does not prove customer interest. /draftoutreach ID requires qualified opportunity, resumed growth control, and no prior linked draft; creates one review-only outreach concept. /outreach reports linked task inventory, never sent count. /task ID, /approve ID REV, /reject ID REV, /regenerate ID REV retain V9.1 version control. Regeneration requires a new draft supplied privately. No external dispatch. Verify recipient permission, source, current requirements and platform rules independently before any future communication.

## Staging
Apply 2026-11-24-v93.sql after V9.1 and V9.2 to backed-up isolated staging; fresh DB uses schema.sql. Keep discovery.enabled false until acceptance, enable only alongside growth and database flags. Verify actual Telegram test bot commands, private CLI, stale evidence review, retention policy, privacy and backup/restore. Do not collect private personal data, bypass restrictions or use automated unsolicited bulk outreach.

## Next
Authorized provider adapters (specific source access, fetch permissions, data provenance, freshness, failure handling, licensing), outreach contact permission and suppression registry, actual reply/delivery receipts, follow-up policy, cross-source deduplication, opportunity-to-lead conversion, owner portal UI, and scheduled monitoring remain pending. No production migration or account connection.
