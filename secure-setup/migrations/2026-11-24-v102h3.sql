CREATE TABLE IF NOT EXISTS growth_carousels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 bundle_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider ENUM('threads','instagram') NOT NULL,
 target_id VARCHAR(32) NOT NULL,
 page_id VARCHAR(32) NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','fetching','processing','assembling','parent_ready','publishing','accepted','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 container_id VARCHAR(32) NULL,
 receipt VARCHAR(32) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_carousel_revision(task_id,revision),
 CONSTRAINT fk_carousel_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_carousel_items (
 carousel_id BIGINT UNSIGNED NOT NULL,
 position_no TINYINT UNSIGNED NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 expires_at DATETIME NULL,
 fetch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 container_id VARCHAR(32) NULL,
 PRIMARY KEY(carousel_id,position_no),
 UNIQUE KEY uq_carousel_child(carousel_id,container_id),
 CONSTRAINT fk_carousel_item FOREIGN KEY(carousel_id) REFERENCES growth_carousels(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
