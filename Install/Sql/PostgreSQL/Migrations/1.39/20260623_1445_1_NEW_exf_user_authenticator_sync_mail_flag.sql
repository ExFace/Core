/*
 * Add mail synchronization flag to user authenticators
 *
 * Adds `sync_mail_flag` to `exf_user_authenticator` if it does not exist.
 * Existing rows receive the default value 1.
 *
 * @author Sergej Riel
 */
-- UP
ALTER TABLE exf_user_authenticator
    ADD COLUMN IF NOT EXISTS sync_mail_flag BOOLEAN NOT NULL DEFAULT TRUE;

-- DOWN
-- Do not delete columns to avoid losing data!