CREATE TABLE IF NOT EXISTS growth_publication_reviews (
 ledger VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 publication_id BIGINT UNSIGNED NOT NULL,
 state_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 outcome ENUM('confirmed_present','confirmed_absent','unresolved') NOT NULL,
 reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(ledger,publication_id,state_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
