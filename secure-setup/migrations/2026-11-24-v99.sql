-- V9.9 owner-only structured expert questions.
CREATE TABLE IF NOT EXISTS growth_expert (
 task_id BIGINT UNSIGNED PRIMARY KEY,
 intent ENUM('assessment','planning','escalation') NOT NULL,
 CONSTRAINT fk_expert_task FOREIGN KEY(task_id) REFERENCES growth_tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
