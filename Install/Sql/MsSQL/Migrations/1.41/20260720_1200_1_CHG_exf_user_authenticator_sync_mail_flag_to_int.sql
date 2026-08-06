/*
 * Ändert exf_user_authenticator.sync_mail_flag von einem booleschen
 * beziehungsweise BIT-ähnlichen Datentyp in einen Integer-Datentyp.
 *
 * PowerUI schreibt für dieses Feld die Werte 1 und 0. Um Probleme mit der
 * Verarbeitung von Boolean- beziehungsweise BIT-Werten zu vermeiden, wird
 * das Flag stattdessen als kleiner Integer gespeichert.
 *
 * Wertzuordnung:
 * - true  -> 1
 * - false -> 0
 *
 * @author AI
 */
-- UP

ALTER TABLE exf_user_authenticator
ADD sync_mail_flag_tmp SMALLINT NOT NULL
    CONSTRAINT DF_exf_user_auth_sync_mail_flag_tmp DEFAULT 0;
GO

UPDATE eua
SET eua.sync_mail_flag_tmp = CASE
    WHEN eua.sync_mail_flag IS NULL THEN 0
    WHEN eua.sync_mail_flag <> 0 THEN 1
    ELSE 0
END
FROM exf_user_authenticator eua;
GO

ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_authenticator_sync_mail_flag;
GO

ALTER TABLE exf_user_authenticator
DROP COLUMN sync_mail_flag;
GO

EXEC sp_rename
    'exf_user_authenticator.sync_mail_flag_tmp',
    'sync_mail_flag',
    'COLUMN';
GO

ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_auth_sync_mail_flag_tmp;
GO

ALTER TABLE exf_user_authenticator
ADD CONSTRAINT DF_exf_user_authenticator_sync_mail_flag
    DEFAULT (0) FOR sync_mail_flag;
GO

-- DOWN

ALTER TABLE exf_user_authenticator
ADD sync_mail_flag_tmp BIT NULL
    CONSTRAINT DF_exf_user_auth_sync_mail_flag_tmp_down DEFAULT 0;
GO

UPDATE eua
SET eua.sync_mail_flag_tmp = CASE
    WHEN eua.sync_mail_flag = 1 THEN 1
    ELSE 0
END
FROM exf_user_authenticator eua;
GO

ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_auth_sync_mail_flag_tmp;
GO

ALTER TABLE exf_user_authenticator
DROP COLUMN sync_mail_flag;
GO

EXEC sp_rename
    'exf_user_authenticator.sync_mail_flag_tmp',
    'sync_mail_flag',
    'COLUMN';
GO

ALTER TABLE exf_user_authenticator
DROP CONSTRAINT DF_exf_user_auth_sync_mail_flag_tmp_down;
GO

ALTER TABLE exf_user_authenticator
ADD CONSTRAINT DF_exf_user_authenticator_sync_mail_flag
    DEFAULT (1) FOR sync_mail_flag;
GO
