/*
 * Adds the slug column to exf_widget_setup, populates it with the page alias
 * from exf_page, then renames page_oid to delete_page_oid (nullable) to signal
 * it is deprecated.
 *
 * @author Saskia Hustinx
 */
-- UP
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup'
      AND COLUMN_NAME = 'slug'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE exf_widget_setup ADD COLUMN slug varchar(2000) NOT NULL DEFAULT ""',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `exf_widget_setup` ws
    INNER JOIN `exf_page` p ON p.`oid` = ws.`page_oid`
SET ws.`slug` = COALESCE(p.`alias`, '');

ALTER TABLE `exf_widget_setup`
    ALTER COLUMN `slug` DROP DEFAULT;

-- drop FKs for deprecated columns
SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup'
      AND CONSTRAINT_NAME = 'FK_widget_setup_page'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
    @fk_exists > 0,
    'ALTER TABLE exf_widget_setup DROP FOREIGN KEY FK_widget_setup_page',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup'
      AND COLUMN_NAME = 'page_oid'
);
SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE exf_widget_setup CHANGE COLUMN page_oid delete_page_oid binary(16) NULL',
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
      AND TABLE_NAME = 'exf_widget_setup'
      AND COLUMN_NAME = 'delete_page_oid'
);
SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE exf_widget_setup CHANGE COLUMN delete_page_oid page_oid binary(16) NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_widget_setup'
      AND CONSTRAINT_NAME = 'FK_widget_setup_page'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE exf_widget_setup ADD CONSTRAINT FK_widget_setup_page FOREIGN KEY (page_oid) REFERENCES exf_page (oid) ON DELETE RESTRICT ON UPDATE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Do not drop slug to avoid data loss!
