CREATE TABLE IF NOT EXISTS growth_native_usage (
 source ENUM('whatsapp','twilio-sms','twilio-calls') PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day_utc DATE NOT NULL,
 metric_count INT UNSIGNED NOT NULL,
 delivered_count INT UNSIGNED NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
