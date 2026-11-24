
-- V9.6 private owner notification preferences and provider receipt history
CREATE TABLE IF NOT EXISTS growth_report_preferences (
 id TINYINT UNSIGNED PRIMARY KEY,
 daily_enabled TINYINT(1) NOT NULL DEFAULT 1,
 errors_enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO growth_report_preferences(id,daily_enabled,errors_enabled) VALUES(1,1,1);
CREATE TABLE IF NOT EXISTS growth_report_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_type ENUM('daily','errors') NOT NULL,
 period_key VARCHAR(24) NOT NULL,
 target_chat_id VARCHAR(24) NOT NULL,
 status ENUM('sending','accepted','failed','unknown') NOT NULL DEFAULT 'sending',
 provider_message_id VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_report_period(report_type,period_key),
 INDEX idx_reports_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
