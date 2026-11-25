-- V10.2G sanitized read-only provider check evidence, no token or refresh secret.
CREATE TABLE IF NOT EXISTS growth_oauth_health (
 provider ENUM('facebook','threads','linkedin','x','google-business','instagram','youtube') PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 target_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('identity_verified','not_verified','error') NOT NULL,
 checked_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
