-- UP
CALL execute_sql_on_missing_column('exf_pwa_dataset', 'data_set_uxon', 'ALTER TABLE exf_pwa_dataset ADD COLUMN data_set_uxon text COLLATE \'utf8mb3_general_ci\' NOT NULL AFTER `offline_strategy_in_model`');


-- DOWN
CALL execute_sql_on_existing_column('exf_pwa_dataset', 'data_set_uxon', 'ALTER TABLE exf_pwa_dataset DROP COLUMN data_set_uxon');
