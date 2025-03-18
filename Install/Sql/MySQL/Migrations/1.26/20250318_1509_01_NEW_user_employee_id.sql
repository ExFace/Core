-- UP
CALL execute_sql_on_missing_column('exf_user', 'employee_id', 'ALTER TABLE exf_user ADD COLUMN employee_id varchar(50) NULL');

-- DOWN
CALL execute_sql_on_existing_column('exf_user', 'employee_id', 'ALTER TABLE exf_user DROP COLUMN employee_id');