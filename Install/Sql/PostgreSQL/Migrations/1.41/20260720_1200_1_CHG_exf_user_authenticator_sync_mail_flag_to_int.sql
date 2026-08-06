/*
 * Ändert exf_user_authenticator.sync_mail_flag von Boolean in Integer.
 *
 * PowerUI schreibt für dieses Feld die Werte 1 und 0. PostgreSQL-Boolean-
 * Spalten sind für dieses Verhalten nicht zuverlässig geeignet. Deshalb wird
 * das Flag auf SMALLINT migriert.
 *
 * Wertzuordnung:
 * - true  -> 1
 * - false -> 0
 *
 * @author AI
 */
-- UP
ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag DROP DEFAULT;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag TYPE SMALLINT
    USING CASE
        WHEN sync_mail_flag IS TRUE THEN 1
        ELSE 0
    END;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag SET DEFAULT 0;

UPDATE exf_user_authenticator AS eua
SET sync_mail_flag = CASE
    WHEN eua.sync_mail_flag IS NULL THEN 0
    WHEN eua.sync_mail_flag <> 0 THEN 1
    ELSE 0
END;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag SET NOT NULL;

-- DOWN
ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag DROP DEFAULT;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag TYPE BOOLEAN
    USING CASE
        WHEN sync_mail_flag = 1 THEN TRUE
        ELSE FALSE
    END;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag SET DEFAULT FALSE;

ALTER TABLE exf_user_authenticator
    ALTER COLUMN sync_mail_flag DROP NOT NULL;
