/*
 * Add PID column to queued tasks
 *
 * Adds the process ID column `pid` to `exf_queued_task` if it does not
 * exist yet. The DOWN section removes the column only if it exists.
 *
 * @author OpenAI
 */
-- UP
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_queued_task'
      AND COLUMN_NAME = 'pid'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE exf_queued_task ADD COLUMN pid INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_queued_task'
      AND COLUMN_NAME = 'pid'
);
SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE exf_queued_task DROP COLUMN pid',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;