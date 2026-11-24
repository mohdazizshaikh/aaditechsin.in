ALTER TABLE growth_carousel_items ADD COLUMN IF NOT EXISTS asset_kind ENUM('image','video') NOT NULL DEFAULT 'image';
CREATE TABLE IF NOT EXISTS growth_communication_reviews (
 aggregate_id BIGINT UNSIGNED PRIMARY KEY,
 due_on DATE NOT NULL,
 status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_comm_review_due(status,due_on),
 CONSTRAINT fk_comm_review_aggregate FOREIGN KEY(aggregate_id) REFERENCES growth_communication_daily(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
