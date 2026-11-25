-- Aaditechs Lead & Partner Operations Hub, MySQL 8+ / MariaDB 10.5+
-- Do not import over a live database without a verified backup.
-- Requires InnoDB and utf8mb4. No example real customer records included.
CREATE TABLE IF NOT EXISTS leads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(24) NOT NULL UNIQUE,
 full_name VARCHAR(100) NOT NULL,
 email VARCHAR(120) NOT NULL,
 phone VARCHAR(20) NOT NULL DEFAULT '',
 service VARCHAR(100) NOT NULL,
 device VARCHAR(100) NOT NULL DEFAULT '',
 message TEXT NOT NULL,
 source VARCHAR(40) NOT NULL DEFAULT 'website',
 status ENUM('new','qualified','responded','converted','closed') NOT NULL DEFAULT 'new',
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_lead_status (status, created_at),
 INDEX idx_lead_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 display_name VARCHAR(120) NOT NULL,
 login_email VARCHAR(160) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 specialties VARCHAR(250) NOT NULL DEFAULT '',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 session_version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_code VARCHAR(24) NOT NULL UNIQUE,
 lead_id BIGINT UNSIGNED NOT NULL,
 partner_id BIGINT UNSIGNED NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT NOT NULL,
 status ENUM('assessment','quote_pending','awaiting_customer_approval','approved','in_progress','work_completed','closed','cancelled') NOT NULL DEFAULT 'assessment',
 customer_quote DECIMAL(12,2) NULL,
 partner_cost DECIMAL(12,2) NULL,
 tracking_token_hash CHAR(64) NOT NULL UNIQUE,
 tracking_expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_jobs_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT,
 CONSTRAINT fk_jobs_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
 INDEX idx_job_partner (partner_id, status),
 INDEX idx_job_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 actor_role VARCHAR(20) NOT NULL,
 actor_label VARCHAR(160) NOT NULL,
 event_type VARCHAR(40) NOT NULL,
 note TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_activity_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 INDEX idx_activity_job (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One owner-alert intent per consented lead. Emails may be delivered more
-- than once after a crash between SMTP acceptance and acknowledgement,
-- include lead reference to allow human deduplication. Never send from HTTP.
CREATE TABLE IF NOT EXISTS notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lead_id BIGINT UNSIGNED NOT NULL UNIQUE,
 status ENUM('pending','sending','sent','dead') NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 locked_at DATETIME NULL,
 sent_at DATETIME NULL,
 last_error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_outbox_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
 INDEX idx_outbox_due (status, next_attempt_at),
 INDEX idx_outbox_lock (status, locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V9.1 additive review-only growth foundation.
CREATE TABLE IF NOT EXISTS growth_tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_id VARCHAR(40) NOT NULL,
 task_type ENUM('research','seo','content','outreach','social','system') NOT NULL,
 title VARCHAR(160) NOT NULL,
 draft TEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('pending_review','approved','rejected','regeneration_requested') NOT NULL DEFAULT 'pending_review',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_growth_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NULL,
 revision INT UNSIGNED NULL,
 action VARCHAR(40) NOT NULL,
 actor VARCHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_growth_audit_task(task_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_control (
 id TINYINT UNSIGNED PRIMARY KEY,
 paused TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO growth_control(id,paused) VALUES(1,1);
CREATE TABLE IF NOT EXISTS growth_telegram_updates (
 update_id BIGINT UNSIGNED PRIMARY KEY,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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


-- V9.3 evidence-backed opportunities, no external contact or delivery.
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


-- V9.3.1 authorized discovery source run evidence
CREATE TABLE IF NOT EXISTS growth_source_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_id VARCHAR(40) NOT NULL,
 status ENUM('running','completed','partial','failed') NOT NULL DEFAULT 'running',
 imported_count INT UNSIGNED NOT NULL DEFAULT 0,
 duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
 skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
 error_code VARCHAR(40) NOT NULL DEFAULT '',
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 INDEX idx_source_runs(source_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V9.4 creative requests contain only catalog-based briefs and review metadata.
CREATE TABLE IF NOT EXISTS growth_creative (
 task_id BIGINT UNSIGNED PRIMARY KEY,
 channel ENUM('facebook','instagram','linkedin','youtube','google-business','x','threads','telegram','blog') NOT NULL,
 objective ENUM('education','awareness','enquiry','case-planning') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_creative_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_creative_results (
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 model_label VARCHAR(100) NOT NULL,
 caption TEXT NOT NULL,
 visual_brief TEXT NOT NULL,
 video_brief TEXT NOT NULL,
 review_notes TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(task_id,revision),
 CONSTRAINT fk_creative_result FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V9.5 scheduled approved Telegram text publication with explicit destination.
CREATE TABLE IF NOT EXISTS growth_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 target_chat_id VARCHAR(24) NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('queued','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'queued',
 provider_message_id VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 claimed_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_publication_revision(task_id,revision),
 INDEX idx_publication_due(status,due_at,id),
 CONSTRAINT fk_publication_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V9.6 private owner notification preferences and provider receipt history
CREATE TABLE IF NOT EXISTS growth_report_preferences (
 id TINYINT UNSIGNED PRIMARY KEY,
 daily_enabled TINYINT(1) NOT NULL DEFAULT 1,
 errors_enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO growth_report_preferences(id,daily_enabled,errors_enabled) VALUES(1,1,1);
CREATE TABLE IF NOT EXISTS growth_report_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_type ENUM('daily','errors') NOT NULL,
 period_key VARCHAR(24) NOT NULL,
 target_chat_id VARCHAR(24) NOT NULL,
 status ENUM('sending','accepted','failed','unknown') NOT NULL DEFAULT 'sending',
 provider_message_id VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_report_period(report_type,period_key),
 INDEX idx_reports_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V9.7 catalog-only worker lease ledger. No plaintext credentials or lease tokens.
CREATE TABLE IF NOT EXISTS growth_creative_leases (
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status ENUM('leased','failed','completed') NOT NULL,
 expires_at DATETIME NOT NULL,
 result_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(task_id,revision),
 CONSTRAINT fk_creative_lease_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V9.8 metadata only. Actual binary media remains on owner's private PC.
CREATE TABLE IF NOT EXISTS growth_media_manifests (
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 image_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 renderer ENUM('local-diffusion','template','native-video') NOT NULL,
 width SMALLINT UNSIGNED NOT NULL,
 height SMALLINT UNSIGNED NOT NULL,
 status ENUM('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 PRIMARY KEY(task_id,revision,manifest_hash),
 CONSTRAINT fk_media_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V9.9 owner-only structured expert questions.
CREATE TABLE IF NOT EXISTS growth_expert (
 task_id BIGINT UNSIGNED PRIMARY KEY,
 intent ENUM('assessment','planning','escalation') NOT NULL,
 CONSTRAINT fk_expert_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.0 source-only SEO audit summaries.
CREATE TABLE IF NOT EXISTS growth_seo_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 page_count SMALLINT UNSIGNED NOT NULL,
 counts_json TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.1 separate Meta text publication ledger.
CREATE TABLE IF NOT EXISTS growth_meta_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 provider ENUM('facebook','threads') NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('queued','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'queued',
 receipt VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 claimed_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_meta_revision(task_id,revision),
 INDEX idx_meta_due(provider,status,due_at,id),
 CONSTRAINT fk_meta_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.1 separate network text publication ledger.
CREATE TABLE IF NOT EXISTS growth_network_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 provider ENUM('linkedin','x','google-business') NOT NULL,
 target_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('queued','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'queued',
 receipt VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 claimed_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_network_revision(task_id,revision),
 INDEX idx_network_due(provider,status,due_at,id),
 CONSTRAINT fk_network_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2A verified private asset ledger and explicit YouTube upload approval.
CREATE TABLE IF NOT EXISTS growth_media_uploads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 visibility ENUM('private','unlisted','public') NOT NULL,
 title VARCHAR(100) NOT NULL,
 description TEXT NOT NULL,
 category_id VARCHAR(3) NOT NULL,
 made_for_kids TINYINT(1) NOT NULL,
 synthetic_content TINYINT(1) NOT NULL,
 status ENUM('pending_review','approved','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 receipt VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_media_upload_revision(task_id,revision),
 CONSTRAINT fk_upload_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2B private Instagram Reel publication ledger.
CREATE TABLE IF NOT EXISTS growth_instagram_uploads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 page_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','uploading','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 container_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 receipt VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_ig_revision(task_id,revision),
 CONSTRAINT fk_ig_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2C Facebook Page photo or video publication, one per creative revision.
CREATE TABLE IF NOT EXISTS growth_facebook_media (
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
 UNIQUE KEY uq_fb_media_revision(task_id,revision),
 CONSTRAINT fk_fb_media_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2D LinkedIn and X single image publication, one per creative revision.
CREATE TABLE IF NOT EXISTS growth_network_media (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_kind ENUM('photo') NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 provider ENUM('linkedin','x') NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','sending','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 receipt VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_nm_revision(task_id,revision),
 CONSTRAINT fk_nm_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2E approved single-image delivery, no permanent public storage.
CREATE TABLE IF NOT EXISTS growth_meta_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider ENUM('threads','instagram') NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 page_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
 byte_count INT UNSIGNED NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','fetching','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 expires_at DATETIME NULL,
 fetch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 container_id VARCHAR(32) NULL,
 receipt VARCHAR(32) NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_meta_image_revision(task_id,revision),
 CONSTRAINT fk_meta_image_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2F reversible private storage maintenance audit.
CREATE TABLE IF NOT EXISTS growth_media_storage_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_name VARCHAR(72) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action ENUM('quarantined','restored') NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_media_storage_asset(asset_name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2G sanitized read-only provider check evidence, no token or refresh secret.
CREATE TABLE IF NOT EXISTS growth_oauth_health (
 provider ENUM('facebook','threads','linkedin','x','google-business','instagram','youtube') PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 target_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('identity_verified','not_verified','error') NOT NULL,
 checked_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V10.2H intermediate image receipts, no provider credentials or upload URLs.
CREATE TABLE IF NOT EXISTS growth_network_media_resources (
 publication_id BIGINT UNSIGNED PRIMARY KEY,
 provider ENUM('linkedin','x') NOT NULL,
 resource_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 phase ENUM('initialized','uploaded') NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_nm_resource FOREIGN KEY(publication_id) REFERENCES growth_network_media(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_linkedin_video (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','uploading','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 video_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 receipt VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_lv_revision(task_id,revision),
 CONSTRAINT fk_lv_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_x_video (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 video_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 target_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','uploading','processing','publishing','accepted','failed','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 video_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 receipt VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(32) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 UNIQUE KEY uq_xv_revision(task_id,revision),
 CONSTRAINT fk_xv_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE growth_meta_images ADD COLUMN IF NOT EXISTS asset_kind ENUM('image','video') NOT NULL DEFAULT 'image';

CREATE TABLE IF NOT EXISTS growth_carousels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 bundle_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider ENUM('threads','instagram') NOT NULL,
 target_id VARCHAR(32) NOT NULL,
 page_id VARCHAR(32) NOT NULL,
 caption TEXT NOT NULL,
 status ENUM('pending_review','approved','fetching','processing','assembling','parent_ready','publishing','accepted','unknown','cancelled') NOT NULL DEFAULT 'pending_review',
 container_id VARCHAR(32) NULL,
 receipt VARCHAR(32) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_carousel_revision(task_id,revision),
 CONSTRAINT fk_carousel_manifest FOREIGN KEY(task_id,revision,manifest_hash) REFERENCES growth_media_manifests(task_id,revision,manifest_hash) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_carousel_items (
 carousel_id BIGINT UNSIGNED NOT NULL,
 position_no TINYINT UNSIGNED NOT NULL,
 asset_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 byte_count INT UNSIGNED NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 expires_at DATETIME NULL,
 fetch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 container_id VARCHAR(32) NULL,
 PRIMARY KEY(carousel_id,position_no),
 UNIQUE KEY uq_carousel_child(carousel_id,container_id),
 CONSTRAINT fk_carousel_item FOREIGN KEY(carousel_id) REFERENCES growth_carousels(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS growth_gmail_snapshot (
 id TINYINT UNSIGNED PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 inbox_count INT UNSIGNED NOT NULL,
 unread_count INT UNSIGNED NOT NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS growth_opportunity_followups (
 opportunity_id BIGINT UNSIGNED PRIMARY KEY,
 due_on DATE NOT NULL,
 status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_followup_due(status,due_on),
 CONSTRAINT fk_followup_opp FOREIGN KEY(opportunity_id) REFERENCES growth_opportunities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_publication_reviews (
 ledger VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 publication_id BIGINT UNSIGNED NOT NULL,
 state_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 outcome ENUM('confirmed_present','confirmed_absent','unresolved') NOT NULL,
 reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(ledger,publication_id,state_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_native_usage (
 source ENUM('whatsapp','twilio-sms','twilio-calls') PRIMARY KEY,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day_utc DATE NOT NULL,
 metric_count INT UNSIGNED NOT NULL,
 delivered_count INT UNSIGNED NULL,
 captured_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE growth_telegram_media MODIFY COLUMN asset_kind ENUM('photo','video','album') NOT NULL;
ALTER TABLE growth_telegram_media ADD COLUMN IF NOT EXISTS album_messages VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL;
CREATE TABLE IF NOT EXISTS growth_whatsapp_inbound (
 event_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day_utc DATE NOT NULL,
 INDEX idx_wa_inbound_day(account_hash,day_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_twilio_inbound (
 event_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 channel ENUM('sms','calls') NOT NULL,
 day_utc DATE NOT NULL,
 INDEX idx_tw_inbound_day(account_hash,channel,day_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE growth_carousel_items ADD COLUMN IF NOT EXISTS asset_kind ENUM('image','video') NOT NULL DEFAULT 'image';
CREATE TABLE IF NOT EXISTS growth_communication_reviews (
 aggregate_id BIGINT UNSIGNED PRIMARY KEY,
 due_on DATE NOT NULL,
 status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_comm_review_due(status,due_on),
 CONSTRAINT fk_comm_review_aggregate FOREIGN KEY(aggregate_id) REFERENCES growth_communication_daily(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS growth_twilio_replay (
 event_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
