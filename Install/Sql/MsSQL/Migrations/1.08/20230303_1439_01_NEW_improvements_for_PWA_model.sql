-- UP

ALTER TABLE exf_pwa_dataset
	ADD rows_at_generation_time INT NULL;
ALTER TABLE exf_pwa_dataset
	ADD offline_strategy_in_model NVARCHAR(20) NULL;
	
ALTER TABLE exf_pwa_action
	ADD object_oid BINARY(16) NULL;
	
ALTER TABLE exf_pwa_action
	DROP CONSTRAINT U_exf_pwa_action_page_trigger_pwa;
ALTER TABLE exf_pwa_action
	ALTER COLUMN trigger_widget_id NVARCHAR(max) NOT NULL;
	
ALTER TABLE exf_pwa_route
	DROP CONSTRAINT U_pwa_route_one_per_action;
ALTER TABLE exf_pwa_route
	ALTER COLUMN pwa_action_oid BINARY(16) NULL;
ALTER TABLE exf_pwa_route
	ALTER COLUMN url NVARCHAR(MAX) NULL;
ALTER TABLE exf_pwa_route
	ADD url_hash AS CONVERT(VARCHAR(32), HashBytes('MD5', url), 2);
ALTER TABLE exf_pwa_route
	ADD CONSTRAINT [U_exf_pwa_route_url_hash_per_pwa] UNIQUE (pwa_oid, url_hash);
	
ALTER TABLE exf_pwa_action
	ADD offline_strategy_in_model NVARCHAR(20) NULL;
EXEC sp_rename 'dbo.exf_pwa_action.offline_strategy', 'offline_strategy_in_facade', 'COLUMN';

ALTER TABLE exf_pwa_action
	ADD trigger_widget_hash AS CONVERT(VARCHAR(32), HashBytes('MD5', trigger_widget_id), 2);
	
-- DOWN

ALTER TABLE exf_pwa_dataset
	DROP COLUMN rows_at_generation_time;
ALTER TABLE exf_pwa_dataset
	DROP COLUMN offline_strategy_in_model;
	
ALTER TABLE exf_pwa_action
	DROP COLUMN object_oid;

ALTER TABLE exf_pwa_route
	DROP CONSTRAINT U_exf_pwa_route_url_hash_per_pwa;	
ALTER TABLE exf_pwa_route
	ALTER COLUMN pwa_action_oid BINARY(16) NOT NULL;
ALTER TABLE exf_pwa_route
	DROP COLUMN url_hash;
	
ALTER TABLE exf_pwa_action
	DROP COLUMN offline_strategy_in_model
	CHANGE COLUMN offline_strategy_in_facade offline_strategy NVARCHAR(20) NOT NULL;

ALTER TABLE exf_pwa_action
	DROP COLUMN trigger_widget_hash;


