-- V10.2E approved single-image delivery, no permanent public storage.
CREATE TABLE IF NOT EXISTS growth_meta_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider ENUM('threads','instagram') NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 page_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
 byte_count INT UNSIGNED NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','fetching','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 expires_at DATETIME NULL,
 fetch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 container_id VARCHAR(32) NULL,
 receipt VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_meta_image_revision(task_id,revision),
 CONSTRAINT fk_meta_image_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
