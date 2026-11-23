-- V9-001: additive, source-only CRM/consent/idempotency foundation.
-- DO NOT RUN on production until V8 schema matches, backup is verified,
-- privacy/retention is approved and staging tests pass. No DROP/ALTER/DELETE.
-- MySQL 8+ / MariaDB 10.5+, InnoDB, utf8mb4, UTC timestamps.

CREATE TABLE IF NOT EXISTS lead_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lead_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('created','classified','contacted','status_changed','owner_note','duplicate_flagged','consent_changed') NOT NULL,
 actor_type ENUM('owner','system','customer') NOT NULL,
 actor_ref VARCHAR(100) NOT NULL,
 public_safe_note VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_v9_events_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT,
 INDEX idx_v9_events_lead (lead_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_attribution (
 lead_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 landing_path VARCHAR(180) NOT NULL DEFAULT '/',
 source VARCHAR(40) NOT NULL DEFAULT 'unknown',
 utm_source VARCHAR(64) NOT NULL DEFAULT '',
 medium VARCHAR(64) NOT NULL DEFAULT '',
 campaign VARCHAR(64) NOT NULL DEFAULT '',
 content VARCHAR(64) NOT NULL DEFAULT '',
 referrer_host VARCHAR(180) NOT NULL DEFAULT '',
 first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_touch_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_v9_attribution_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_consent (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lead_id BIGINT UNSIGNED NOT NULL,
 purpose ENUM('inquiry_contact','marketing_email','review_request') NOT NULL,
 decision ENUM('granted','withdrawn') NOT NULL,
 notice_version VARCHAR(60) NOT NULL,
 source VARCHAR(40) NOT NULL,
 evidence_ref VARCHAR(100) NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_v9_consent_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT,
 INDEX idx_v9_consent_latest (lead_id, purpose, occurred_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_suppression (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 contact_hash CHAR(64) NOT NULL,
 channel ENUM('email','sms','phone') NOT NULL,
 reason ENUM('withdrawal','bounce','manual') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NULL,
 UNIQUE KEY uq_v9_suppression (contact_hash, channel),
 INDEX idx_v9_suppression_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Request hashes use HMAC with the private rate_limit_salt; never store raw keys,
-- body, email or IP in this table. A unique key serializes concurrent retries.
CREATE TABLE IF NOT EXISTS lead_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 idempotency_hash CHAR(64) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 lead_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 UNIQUE KEY uq_v9_request_key (idempotency_hash),
 INDEX idx_v9_requests_expiry (expires_at),
 CONSTRAINT fk_v9_requests_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
