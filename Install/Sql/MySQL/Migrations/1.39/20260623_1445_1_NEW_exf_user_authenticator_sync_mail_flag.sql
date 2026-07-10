/*
 * Add mail synchronization flag to user authenticators
 *
 * Adds `sync_mail_flag` to `exf_user_authenticator` if it does not exist.
 * Existing rows receive the default value 1.
 *
 * @author Sergej Riel
 */
-- UP
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_user_authenticator'
      AND COLUMN_NAME = 'sync_mail_flag'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE exf_user_authenticator ADD COLUMN sync_mail_flag TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
-- Do not delete columns to avoid losing data!