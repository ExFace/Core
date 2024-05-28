-- UP
CALL execute_sql_on_missing_column('exf_user_role_external', 'keep_manual_assignments_flag', 'ALTER TABLE exf_user_role_external ADD COLUMN keep_manual_assignments_flag tinyint NULL');

-- DOWN
CALL execute_sql_on_existing_column('exf_user_role_external', 'keep_manual_assignments_flag', 'ALTER TABLE exf_user_role_external DROP COLUMN keep_manual_assignments_flag');
