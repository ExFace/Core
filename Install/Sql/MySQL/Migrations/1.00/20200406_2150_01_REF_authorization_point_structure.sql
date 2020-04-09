-- UP

ALTER TABLE `exf_auth_point`
	CHANGE COLUMN `combining_algorithm` `combining_algorithm_in_app` VARCHAR(30) NOT NULL COLLATE 'utf8_general_ci' AFTER `default_effect`,
	CHANGE COLUMN `default_effect` `default_effect_in_app` CHAR(1) NOT NULL DEFAULT 'P' COLLATE 'utf8_general_ci' AFTER `app_oid`,
	CHANGE COLUMN `active_flag` `disabled_flag` TINYINT(1) NOT NULL DEFAULT '0' AFTER `combining_algorithm_in_app`,
	ADD COLUMN `default_effect_local` CHAR(1) NULL DEFAULT NULL AFTER `default_effect_in_app`,
	ADD COLUMN `combining_algorithm_local` VARCHAR(30) NULL DEFAULT NULL AFTER `combining_algorithm_in_app`,
	ADD COLUMN `policy_prototype_class` VARCHAR(200) NOT NULL AFTER `disabled_flag`;
	
UPDATE `exf_auth_point` SET `disabled_flag` = 0;

ALTER TABLE `exf_auth_policy`
	CHANGE COLUMN `effect` `effect` CHAR(1) NOT NULL COLLATE 'utf8_general_ci' AFTER `descr`,
	ADD COLUMN `disabled_flag` TINYINT(1) NOT NULL DEFAULT '0' AFTER `effect`;
	
ALTER TABLE `exf_auth_policy`
	ADD INDEX `ModelLoader queries` (`auth_point_oid`, `disabled_flag`, `target_user_role_oid`);
	
-- DOWN

ALTER TABLE `exf_auth_point`
	CHANGE COLUMN `combining_algorithm_in_app` `combining_algorithm` VARCHAR(30) NOT NULL COLLATE 'utf8_general_ci' AFTER `default_effect_in_app`,
	CHANGE COLUMN `default_effect_in_app` `default_effect` CHAR(1) NOT NULL DEFAULT 'P' COLLATE 'utf8_general_ci' AFTER `app_oid`,
	CHANGE COLUMN `disabled_flag` `active_flag` TINYINT(1) NOT NULL DEFAULT '0' AFTER `combining_algorithm`,
	DROP COLUMN `default_effect_local`,
	DROP COLUMN `combining_algorithm_local`,
	DROP COLUMN `policy_prototype_class`;
	
UPDATE `exf_auth_point` SET `active_flag` = 1;

ALTER TABLE `exf_auth_policy`
	DROP COLUMN `disabled_flag`;
	
ALTER TABLE `exf_auth_policy`
	DROP INDEX `ModelLoader queries`;

