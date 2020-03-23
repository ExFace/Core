-- UP

ALTER TABLE `exf_auth_policy`
	CHANGE COLUMN `name` `name` VARCHAR(100) NULL DEFAULT '' AFTER `modified_by_user_oid`,
	CHANGE COLUMN `descr` `descr` VARCHAR(200) NULL DEFAULT '' AFTER `name`,
	CHANGE COLUMN `target_action_selector` `target_object_action_oid` BINARY(16) NULL DEFAULT NULL AFTER `target_object_oid`,
	ADD COLUMN `target_action_class_path` VARCHAR(255) NULL DEFAULT NULL AFTER `target_object_action_oid`,
	ADD COLUMN `target_facade_class_path` VARCHAR(255) NULL DEFAULT NULL AFTER `target_action_class_path`,
	CHANGE COLUMN `condition_uxon` `condition_uxon` MEDIUMTEXT NULL DEFAULT NULL AFTER `target_facade_class_path`;
		
-- DOWN

ALTER TABLE `exf_auth_policy`
	DROP COLUMN `target_action_class_path`,
	DROP COLUMN `target_facade_class_path`;