
-- V9.3 evidence-backed opportunities; no external contact or delivery.
CREATE TABLE IF NOT EXISTS growth_opportunities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 source_type ENUM('tender','company_announcement','authorized_directory','referral','manual_research') NOT NULL,
 source_url VARCHAR(500) NOT NULL,
 organization VARCHAR(160) NOT NULL,
 location_label VARCHAR(120) NOT NULL,
 evidence VARCHAR(1200) NOT NULL,
 observed_on DATE NOT NULL,
 signal_type ENUM('explicit_request','expansion','upgrade','general_fit') NOT NULL,
 service_id VARCHAR(40) NOT NULL,
 relevance_score TINYINT UNSIGNED NOT NULL,
 status ENUM('pending_review','qualified','rejected') NOT NULL DEFAULT 'pending_review',
 outreach_task_id BIGINT UNSIGNED NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_opportunity_review(status,relevance_score,id),
 CONSTRAINT fk_growth_opp_task FOREIGN KEY(outreach_task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
