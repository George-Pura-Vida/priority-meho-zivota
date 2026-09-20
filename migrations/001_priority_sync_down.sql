SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS priority_reviews;
DROP TABLE IF EXISTS priority_tasks;
DROP TABLE IF EXISTS priority_goals;
DROP TABLE IF EXISTS priority_state;
DELETE FROM schema_migrations WHERE migration='001_priority_sync';
SET FOREIGN_KEY_CHECKS=1;