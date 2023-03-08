-- UP

ALTER TABLE `exf_pwa_dataset`
	ADD COLUMN `rows_at_generation_time` INT NULL AFTER `user_defined_flag`,
	ADD COLUMN `offline_strategy_in_model` VARCHAR(20) NULL AFTER `rows_at_generation_time`;
	
ALTER TABLE `exf_pwa_action`
	ADD COLUMN `object_oid` BINARY(16) NULL AFTER `action_alias`;
	
ALTER TABLE `exf_pwa_action`
	DROP INDEX `Unique_per_page_widget_and_PWA`;
ALTER TABLE `exf_pwa_action`
	CHANGE COLUMN `trigger_widget_id` `trigger_widget_id` TEXT NOT NULL AFTER `page_oid`;
	
ALTER TABLE `exf_pwa_route`
	DROP INDEX `Max_1_route_per_PWA_action`;
ALTER TABLE `exf_pwa_route`
	CHANGE COLUMN `pwa_action_oid` `pwa_action_oid` BINARY(16) NULL,
	CHANGE COLUMN `url` `url` TEXT NOT NULL;
ALTER TABLE `exf_pwa_route`
	ADD COLUMN `url_hash` VARCHAR(32) AS (md5(url)) STORED AFTER `url`;
ALTER TABLE `exf_pwa_route`
	ADD UNIQUE INDEX `URL hash unique per PWA` (`pwa_oid`, `url_hash`);
	
ALTER TABLE `exf_pwa_action`
	CHANGE COLUMN `offline_strategy` `offline_strategy_in_facade` VARCHAR(20) NOT NULL,
	ADD COLUMN `offline_strategy_in_model` VARCHAR(20) AFTER `offline_strategy_in_facade`;

ALTER TABLE `exf_pwa_action`
	ADD COLUMN `trigger_widget_hash` VARCHAR(32) AS (md5(trigger_widget_id)) STORED AFTER `trigger_widget_id`;
ALTER TABLE `exf_pwa_action`
	ADD UNIQUE INDEX `Action unique per PWA, page and widget` (`pwa_oid`, `action_alias`, `page_oid`, `trigger_widget_hash`) USING BTREE;

	
-- DOWN

ALTER TABLE `exf_pwa_action`
	DROP INDEX `Action unique per PWA, page and widget`;
	
ALTER TABLE `exf_pwa_action`
	DROP COLUMN `offline_strategy_in_model`,
	CHANGE COLUMN `offline_strategy_in_facade` `offline_strategy` VARCHAR(20) NOT NULL;

ALTER TABLE `exf_pwa_action`
	DROP COLUMN `trigger_widget_hash`;

ALTER TABLE `exf_pwa_dataset`
	DROP COLUMN `rows_at_generation_time`,
	DROP COLUMN `offline_strategy_in_model`;
	
ALTER TABLE `exf_pwa_action`
	DROP COLUMN `object_oid`;
	
ALTER TABLE `exf_pwa_route`
	DROP INDEX `URL hash unique per PWA`;
ALTER TABLE `exf_pwa_route`
	CHANGE COLUMN `pwa_action_oid` `pwa_action_oid` BINARY(16) NOT NULL AFTER `pwa_oid`;
ALTER TABLE `exf_pwa_route`
	DROP COLUMN `url_hash`;


