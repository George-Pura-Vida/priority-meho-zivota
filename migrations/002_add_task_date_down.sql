DROP INDEX idx_priority_tasks_day ON priority_tasks;
DROP INDEX idx_priority_tasks_priority ON priority_tasks;
ALTER TABLE priority_tasks DROP COLUMN task_date;
CREATE INDEX idx_priority_tasks_priority
 ON priority_tasks (user_id,done,importance,urgency);
DELETE FROM schema_migrations WHERE migration='002_add_task_date';