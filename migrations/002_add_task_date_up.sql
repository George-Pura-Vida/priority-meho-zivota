ALTER TABLE priority_tasks
 ADD COLUMN IF NOT EXISTS task_date DATE NULL AFTER user_id;

CREATE INDEX idx_priority_tasks_day
 ON priority_tasks (user_id,task_date,done);

/* If upgrading an older schema that already has idx_priority_tasks_priority,
   drop that old index before running the CREATE below. */
CREATE INDEX idx_priority_tasks_priority
 ON priority_tasks (user_id,task_date,done,importance,urgency);

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('002_add_task_date');