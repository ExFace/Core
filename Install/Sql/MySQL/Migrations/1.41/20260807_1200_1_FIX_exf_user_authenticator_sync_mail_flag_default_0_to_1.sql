/*
 * Changes the default value of the sync_mail_flag column in the exf_user_authenticator table from 0 to 1.
 *
 * @author Sergej Riel
 */
-- UP
    ALTER TABLE exf_user_authenticator ALTER COLUMN sync_mail_flag SET DEFAULT 1

-- DOWN
    ALTER TABLE exf_user_authenticator ALTER COLUMN sync_mail_flag SET DEFAULT 0