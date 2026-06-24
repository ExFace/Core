/*
 * Add mail synchronization flag to user authenticators
 *
 * Adds `sync_mail_flag` to `exf_user_authenticator` if it does not exist.
 * Existing rows receive the default value 1.
 *
 * @author Sergej Riel
 */
-- UP
IF COL_LENGTH('dbo.exf_user_authenticator', 'sync_mail_flag') IS NULL
BEGIN
    ALTER TABLE dbo.exf_user_authenticator
        ADD sync_mail_flag BIT NOT NULL
            CONSTRAINT DF_exf_user_authenticator_sync_mail_flag DEFAULT (1);
END;

-- DOWN
-- Do not delete columns to avoid losing data!