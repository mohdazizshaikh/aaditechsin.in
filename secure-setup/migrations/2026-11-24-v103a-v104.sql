CREATE TABLE IF NOT EXISTS growth_gmail_snapshot (
 id TINYINT UNSIGNED PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 inbox_count INT UNSIGNED NOT NULL,
 unread_count INT UNSIGNED NOT NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_opportunity_followups (
 opportunity_id BIGINT UNSIGNED PRIMARY KEY,
 due_on DATE NOT NULL,
 status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_followup_due(status,due_on),
 CONSTRAINT fk_followup_opp FOREIGN KEY(opportunity_id) REFERENCES growth_opportunities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
