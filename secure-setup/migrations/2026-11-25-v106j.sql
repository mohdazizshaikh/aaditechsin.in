CREATE TABLE IF NOT EXISTS growth_search_console_daily (
 property_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day_pt DATE NOT NULL,
 clicks INT UNSIGNED NOT NULL,
 impressions INT UNSIGNED NOT NULL,
 average_position DECIMAL(12,4) NULL,
 captured_at DATETIME NOT NULL,
 PRIMARY KEY(property_hash,generation,day_pt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_live_seo_snapshot (
 id TINYINT UNSIGNED PRIMARY KEY,
 report_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 counts_json TEXT NOT NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
