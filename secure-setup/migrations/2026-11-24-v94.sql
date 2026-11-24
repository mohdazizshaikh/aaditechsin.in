
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
