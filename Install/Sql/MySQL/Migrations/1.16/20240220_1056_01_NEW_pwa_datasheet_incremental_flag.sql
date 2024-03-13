-- UP
CALL execute_sql_on_missing_column('exf_pwa_dataset', 'incremental_flag', 'ALTER TABLE exf_pwa_dataset ADD COLUMN incremental_flag tinyint NOT NULL');


-- DOWN
CALL execute_sql_on_existing_column('exf_pwa_dataset', 'incremental_flag', 'ALTER TABLE exf_pwa_dataset DROP COLUMN incremental_flag');
