-- UP
CALL execute_sql_on_missing_column('exf_user_role_users', 'authenticator_id', 'ALTER TABLE exf_user_role_users ADD COLUMN authenticator_id varchar(100) COLLATE \'utf8mb3_general_ci\' NULL AFTER `user_oid`');

-- DOWN
CALL execute_sql_on_existing_column('exf_user_role_users', 'authenticator_id', 'ALTER TABLE exf_user_role_users DROP COLUMN authenticator_id');
