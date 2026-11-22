-- V7 -> V8 additive migration for already-created STAGING database tables.
-- IMPORT ONLY ONCE, after a verified SQL backup. Schema already includes columns for fresh installs.
-- Database selection must be confirmed in phpMyAdmin; never run against unverified production.
ALTER TABLE partners ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE jobs ADD COLUMN tracking_expires_at DATETIME NULL;
-- Legacy links expire after 90 days. Owner can manually rotate any link sooner.
UPDATE jobs SET tracking_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY) WHERE tracking_expires_at IS NULL;
ALTER TABLE jobs MODIFY tracking_expires_at DATETIME NOT NULL;
