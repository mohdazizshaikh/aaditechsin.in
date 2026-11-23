-- Aaditechs Lead & Partner Operations Hub, MySQL 8+ / MariaDB 10.5+
-- Do not import over a live database without a verified backup.
-- Requires InnoDB and utf8mb4. No example real customer records included.
CREATE TABLE IF NOT EXISTS leads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(24) NOT NULL UNIQUE,
 full_name VARCHAR(100) NOT NULL,
 email VARCHAR(120) NOT NULL,
 phone VARCHAR(20) NOT NULL DEFAULT '',
 service VARCHAR(100) NOT NULL,
 device VARCHAR(100) NOT NULL DEFAULT '',
 message TEXT NOT NULL,
 source VARCHAR(40) NOT NULL DEFAULT 'website',
 status ENUM('new','qualified','responded','converted','closed') NOT NULL DEFAULT 'new',
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_lead_status (status, created_at),
 INDEX idx_lead_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 display_name VARCHAR(120) NOT NULL,
 login_email VARCHAR(160) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 specialties VARCHAR(250) NOT NULL DEFAULT '',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 session_version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_code VARCHAR(24) NOT NULL UNIQUE,
 lead_id BIGINT UNSIGNED NOT NULL,
 partner_id BIGINT UNSIGNED NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT NOT NULL,
 status ENUM('assessment','quote_pending','awaiting_customer_approval','approved','in_progress','work_completed','closed','cancelled') NOT NULL DEFAULT 'assessment',
 customer_quote DECIMAL(12,2) NULL,
 partner_cost DECIMAL(12,2) NULL,
 tracking_token_hash CHAR(64) NOT NULL UNIQUE,
 tracking_expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_jobs_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT,
 CONSTRAINT fk_jobs_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
 INDEX idx_job_partner (partner_id, status),
 INDEX idx_job_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 actor_role VARCHAR(20) NOT NULL,
 actor_label VARCHAR(160) NOT NULL,
 event_type VARCHAR(40) NOT NULL,
 note TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_activity_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 INDEX idx_activity_job (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One owner-alert intent per consented lead. Emails may be delivered more
-- than once after a crash between SMTP acceptance and acknowledgement,
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
