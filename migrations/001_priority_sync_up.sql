SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
 migration VARCHAR(100) NOT NULL,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS priority_state (
 user_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 goal_horizon ENUM('10 let','5 let','1 rok','Měsíc') NOT NULL DEFAULT '10 let',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id),
 CONSTRAINT chk_priority_state_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS priority_goals (
 id CHAR(36) NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(255) NOT NULL,
 area ENUM('Zdraví','Finance','Vztahy','Rozvoj') NOT NULL,
 progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
 horizon ENUM('10 let','5 let','1 rok','Měsíc') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_priority_goals_user (user_id),
 KEY idx_priority_goals_user_horizon (user_id,horizon),
 CONSTRAINT chk_priority_goal_progress CHECK (progress BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS priority_tasks (
 id CHAR(36) NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 task_date DATE NULL,
 name VARCHAR(255) NOT NULL,
 area ENUM('Zdraví','Finance','Vztahy','Rozvoj') NOT NULL,
 importance TINYINT UNSIGNED NOT NULL,
 urgency TINYINT UNSIGNED NOT NULL,
 minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 done BOOLEAN NOT NULL DEFAULT FALSE,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_priority_tasks_user (user_id),
 KEY idx_priority_tasks_user_done (user_id,done),
 KEY idx_priority_tasks_day (user_id,task_date,done),
 KEY idx_priority_tasks_priority (user_id,task_date,done,importance,urgency),
 CONSTRAINT chk_priority_task_importance CHECK (importance BETWEEN 0 AND 10),
 CONSTRAINT chk_priority_task_urgency CHECK (urgency BETWEEN 0 AND 10),
 CONSTRAINT chk_priority_task_minutes CHECK (minutes <= 1440)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS priority_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 review_date DATE NOT NULL,
 score TINYINT UNSIGNED NULL,
 win TEXT NULL,
 waste TEXT NULL,
 tomorrow TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_priority_review_day (user_id,review_date),
 KEY idx_priority_reviews_user_date (user_id,review_date),
 CONSTRAINT chk_priority_review_score CHECK (score IS NULL OR score BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO priority_state (user_id,version,goal_horizon)
VALUES (1,1,'10 let')
ON DUPLICATE KEY UPDATE user_id=user_id;

INSERT IGNORE INTO schema_migrations (migration) VALUES ('001_priority_sync');