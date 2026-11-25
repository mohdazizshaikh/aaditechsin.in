ALTER TABLE growth_telegram_media MODIFY COLUMN asset_kind ENUM('photo','video','album') NOT NULL;
ALTER TABLE growth_telegram_media ADD COLUMN IF NOT EXISTS album_messages VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL;
CREATE TABLE IF NOT EXISTS growth_whatsapp_inbound (
 event_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day_utc DATE NOT NULL,
 INDEX idx_wa_inbound_day(account_hash,day_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
