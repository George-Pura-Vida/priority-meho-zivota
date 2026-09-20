/* Idempotent legacy upgrade. Safe after 001_priority_sync and safe to rerun. */
SET @db := DATABASE();

SET @has_col := (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_tasks' AND COLUMN_NAME='task_date'
);
SET @sql := IF(@has_col=0,
 'ALTER TABLE priority_tasks ADD COLUMN task_date DATE NULL AFTER user_id',
 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_day_idx := (
 SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_tasks' AND INDEX_NAME='idx_priority_tasks_day'
);
SET @sql := IF(@has_day_idx=0,
 'CREATE INDEX idx_priority_tasks_day ON priority_tasks (user_id,task_date,done)',
 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @priority_cols := (
 SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
 FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_tasks' AND INDEX_NAME='idx_priority_tasks_priority'
);
SET @sql := IF(@priority_cols IS NOT NULL AND @priority_cols <> 'user_id,task_date,done,importance,urgency',
 'DROP INDEX idx_priority_tasks_priority ON priority_tasks',
 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_priority_idx := (
 SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_tasks' AND INDEX_NAME='idx_priority_tasks_priority'
);
SET @sql := IF(@has_priority_idx=0,
 'CREATE INDEX idx_priority_tasks_priority ON priority_tasks (user_id,task_date,done,importance,urgency)',
 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration) VALUES ('002_add_task_date');
