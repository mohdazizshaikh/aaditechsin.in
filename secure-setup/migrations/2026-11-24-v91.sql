
-- V9.1 additive review-only growth foundation.
CREATE TABLE IF NOT EXISTS growth_tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_id VARCHAR(40) NOT NULL,
 task_type ENUM('research','seo','content','outreach','social','system') NOT NULL,
 title VARCHAR(160) NOT NULL,
 draft TEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('pending_review','approved','rejected','regeneration_requested') NOT NULL DEFAULT 'pending_review',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_growth_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NULL,
 revision INT UNSIGNED NULL,
 action VARCHAR(40) NOT NULL,
 actor VARCHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_growth_audit_task(task_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_control (
 id TINYINT UNSIGNED PRIMARY KEY,
 paused TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO growth_control(id,paused) VALUES(1,1);
CREATE TABLE IF NOT EXISTS growth_telegram_updates (
 update_id BIGINT UNSIGNED PRIMARY KEY,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
