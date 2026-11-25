-- V10.2F reversible private storage maintenance audit.
CREATE TABLE IF NOT EXISTS growth_media_storage_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_name VARCHAR(72) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action ENUM('quarantined','restored') NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_media_storage_asset(asset_name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
