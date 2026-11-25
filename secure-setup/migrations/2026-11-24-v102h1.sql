CREATE TABLE IF NOT EXISTS growth_linkedin_video (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','uploading','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 video_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 receipt VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_lv_revision(task_id,revision),
 CONSTRAINT fk_lv_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
