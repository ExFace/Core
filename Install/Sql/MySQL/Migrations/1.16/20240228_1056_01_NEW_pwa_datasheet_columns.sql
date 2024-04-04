-- UP
CALL execute_sql_on_missing_column('exf_pwa_dataset', 'incremental_columns', 'ALTER TABLE exf_pwa_dataset ADD COLUMN incremental_columns int');
CALL execute_sql_on_missing_column('exf_pwa_dataset', 'columns', 'ALTER TABLE exf_pwa_dataset ADD COLUMN columns int');


-- DOWN
CALL execute_sql_on_existing_column('exf_pwa_dataset', 'incremental_columns', 'ALTER TABLE exf_pwa_dataset DROP COLUMN incremental_columns');
CALL execute_sql_on_existing_column('exf_pwa_dataset', 'columns', 'ALTER TABLE exf_pwa_dataset DROP COLUMN columns ');
