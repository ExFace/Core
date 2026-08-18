/*
 * Ändert exf_user_authenticator.sync_mail_flag von Boolean in Integer.
 *
 * PowerUI schreibt für dieses Feld die Werte 1 und 0. Um datenbankspezifische
 * Probleme bei der Verarbeitung boolescher Werte zu vermeiden, wird das Flag
 * stattdessen als kleiner Integer gespeichert.
 *
 * Wertzuordnung:
 * - true  -> 1
 * - false -> 0
 *
 * @author AI
 */
-- UP
ALTER TABLE exf_user_authenticator
    MODIFY COLUMN sync_mail_flag SMALLINT NOT NULL DEFAULT 0;

UPDATE exf_user_authenticator AS eua
SET eua.sync_mail_flag = CASE
    WHEN eua.sync_mail_flag IS NULL THEN 0
    WHEN eua.sync_mail_flag <> 0 THEN 1
    ELSE 0
END;

-- DOWN
ALTER TABLE exf_user_authenticator
    MODIFY COLUMN sync_mail_flag BOOLEAN NULL DEFAULT NULL;
