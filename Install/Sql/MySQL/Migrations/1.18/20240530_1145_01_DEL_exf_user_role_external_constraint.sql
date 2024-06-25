-- UP
ALTER TABLE exf_user_role_external DROP INDEX `Alias unique per authenticator`;

-- DOWN
-- not needed as we can't revert that change as data might exist that does not fit that constraint