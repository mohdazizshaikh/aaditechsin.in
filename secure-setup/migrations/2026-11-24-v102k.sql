-- V10.2K Telegram channel photo or video publication, one per creative revision.
CREATE TABLE IF NOT EXISTS growth_telegram_media (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_kind ENUM('photo','video') NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 receipt VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_tg_media_revision(task_id,revision),
 CONSTRAINT fk_tg_media_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_communication_daily (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 channel ENUM('email','whatsapp','sms','calls') NOT NULL,
 day_utc DATE NOT NULL,
 received_count INT UNSIGNED NOT NULL,
 answered_count INT UNSIGNED NOT NULL,
 missed_count INT UNSIGNED NOT NULL,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_communication_day(channel,day_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
