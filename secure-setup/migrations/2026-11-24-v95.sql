
-- V9.5 scheduled approved Telegram text publication with explicit destination.
CREATE TABLE IF NOT EXISTS growth_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 target_chat_id VARCHAR(24) NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('queued','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'queued',
 provider_message_id VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 claimed_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_publication_revision(task_id,revision),
 INDEX idx_publication_due(status,due_at,id),
 CONSTRAINT fk_publication_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
