-- V10.2A verified private asset ledger and explicit YouTube upload approval.
CREATE TABLE IF NOT EXISTS growth_media_uploads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 visibility ENUM('private','unlisted','public') NOT NULL,
 title VARCHAR(100) NOT NULL,
 description TEXT NOT NULL,
 category_id VARCHAR(3) NOT NULL,
 made_for_kids TINYINT(1) NOT NULL,
 synthetic_content TINYINT(1) NOT NULL,
 status ENUM('pending_review','approved','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 receipt VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_media_upload_revision(task_id,revision),
 CONSTRAINT fk_upload_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
