-- Apply to backed-up V8.3 databases exactly once before enabling queued mail.
-- Empty schema installs already include this table; CREATE IF NOT EXISTS is safe.

-- One owner-alert intent per consented lead. Emails may be delivered more
-- than once after a crash between SMTP acceptance and acknowledgement;
-- include lead reference to allow human deduplication. Never send from HTTP.
CREATE TABLE IF NOT EXISTS notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lead_id BIGINT UNSIGNED NOT NULL UNIQUE,
 status ENUM('pending','sending','sent','dead') NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 locked_at DATETIME NULL,
 sent_at DATETIME NULL,
 last_error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_outbox_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
 INDEX idx_outbox_due (status, next_attempt_at),
 INDEX idx_outbox_lock (status, locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
