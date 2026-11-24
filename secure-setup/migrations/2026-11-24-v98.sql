-- V9.8 metadata only. Actual binary media remains on owner's private PC.
CREATE TABLE IF NOT EXISTS growth_media_manifests (
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 image_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 renderer ENUM('local-diffusion','template') NOT NULL,
 width SMALLINT UNSIGNED NOT NULL,
 height SMALLINT UNSIGNED NOT NULL,
 status ENUM('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 PRIMARY KEY(task_id,revision,manifest_hash),
 CONSTRAINT fk_media_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
