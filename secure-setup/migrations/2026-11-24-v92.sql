
-- V9.2 optional first-party analytics. No raw IP, user agent, referrer or URL query.
CREATE TABLE IF NOT EXISTS growth_analytics_events (
 event_day DATE NOT NULL,
 event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 page_id VARCHAR(40) NOT NULL,
 event_type ENUM('page_view','service_interest','call_click','email_click','chat_open') NOT NULL,
 service_id VARCHAR(40) NOT NULL DEFAULT '',
 channel ENUM('direct','search','social','email','referral','other') NOT NULL,
 automation_signal TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(event_day,event_id),
 INDEX idx_analytics_session(event_day,session_hash),
 INDEX idx_analytics_report(event_day,event_type,page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_analytics_budget (
 event_day DATE PRIMARY KEY,
 accepted_count INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
