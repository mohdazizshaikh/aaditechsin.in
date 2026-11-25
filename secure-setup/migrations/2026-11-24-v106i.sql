CREATE TABLE IF NOT EXISTS growth_search_console (
 id TINYINT UNSIGNED PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 property_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 start_day DATE NOT NULL,
 end_day DATE NOT NULL,
 days_reported TINYINT UNSIGNED NOT NULL,
 clicks BIGINT UNSIGNED NOT NULL,
 impressions BIGINT UNSIGNED NOT NULL,
 average_position DECIMAL(12,4) NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
