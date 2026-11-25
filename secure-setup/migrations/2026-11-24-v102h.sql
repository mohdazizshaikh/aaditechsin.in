-- V10.2H intermediate image receipts, no provider credentials or upload URLs.
CREATE TABLE IF NOT EXISTS growth_network_media_resources (
 publication_id BIGINT UNSIGNED PRIMARY KEY,
 provider ENUM('linkedin','x') NOT NULL,
 resource_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 phase ENUM('initialized','uploaded') NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_nm_resource FOREIGN KEY(publication_id) REFERENCES growth_network_media(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
