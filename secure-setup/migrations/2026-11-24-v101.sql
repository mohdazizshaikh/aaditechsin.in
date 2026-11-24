-- V10.1 separate Meta text publication ledger.
CREATE TABLE IF NOT EXISTS growth_meta_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 provider ENUM('facebook','threads') NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('queued','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'queued',
 receipt VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 claimed_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_meta_revision(task_id,revision),
 INDEX idx_meta_due(provider,status,due_at,id),
 CONSTRAINT fk_meta_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
