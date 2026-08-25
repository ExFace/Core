/*
 * Prepares exf_widget_setup for the widget setup cleanup.
 *
 * 1. Creates a full backup copy of the table as exf_widget_setup_backup
 *    (structure + data) before the cleanup converts old setups.
 * 2. Adds an orphaned_flag column used to mark setups whose widget could no
 *    longer be found during the cleanup.
 *
 * @author Saskia Hustinx
 */
-- UP
-- Create a full backup copy of the table (structure + data) only if missing
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup_backup'
);
SET @sql := IF(
    @tbl_exists = 0,
    'CREATE TABLE `exf_widget_setup_backup` AS SELECT * FROM `exf_widget_setup`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add the orphaned_flag column only if it does not exist yet
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup'
      AND COLUMN_NAME = 'orphaned_flag'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE exf_widget_setup ADD COLUMN orphaned_flag tinyint NOT NULL DEFAULT ''0''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- Keep the orphaned_flag column and exf_widget_setup_backup on purpose to avoid
-- losing the cleanup results and the safety backup.
