
-- V9.3.1 authorized discovery source run evidence
CREATE TABLE IF NOT EXISTS growth_source_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_id VARCHAR(40) NOT NULL,
 status ENUM('running','completed','partial','failed') NOT NULL DEFAULT 'running',
 imported_count INT UNSIGNED NOT NULL DEFAULT 0,
 duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
 skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_code VARCHAR(40) NOT NULL DEFAULT '',
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 INDEX idx_source_runs(source_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
