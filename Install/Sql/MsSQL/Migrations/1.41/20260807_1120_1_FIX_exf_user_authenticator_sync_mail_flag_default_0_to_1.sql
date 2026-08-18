/*
 * Changes the default value of the sync_mail_flag column in the exf_user_authenticator table from 0 to 1.
 *
 * @author Sergej Riel
 */
-- UP
ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_authenticator_sync_mail_flag;
GO

ALTER TABLE exf_user_authenticator
ADD CONSTRAINT DF_exf_user_authenticator_sync_mail_flag
    DEFAULT (1) FOR sync_mail_flag;
GO

-- DOWN
ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_authenticator_sync_mail_flag;
GO

ALTER TABLE exf_user_authenticator
ADD CONSTRAINT DF_exf_user_authenticator_sync_mail_flag
    DEFAULT (0) FOR sync_mail_flag;
GO
