-- V9.7 catalog-only worker lease ledger. No plaintext credentials or lease tokens.
CREATE TABLE IF NOT EXISTS growth_creative_leases (
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status ENUM('leased','failed','completed') NOT NULL,
 expires_at DATETIME NOT NULL,
 result_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(task_id,revision),
 CONSTRAINT fk_creative_lease_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
