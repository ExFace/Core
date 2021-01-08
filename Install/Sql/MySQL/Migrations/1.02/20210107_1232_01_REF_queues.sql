-- UP

ALTER TABLE `exf_queued_task`
	CHANGE COLUMN `owner` `owner_oid` BINARY(16) NOT NULL AFTER `task_uxon`,
	CHANGE COLUMN `queue` `queue_oid` BINARY(16) NULL AFTER `parent_item_oid`,
	ADD COLUMN `channel` VARCHAR(50) NULL DEFAULT NULL AFTER `message_id`,
	ADD COLUMN `processed_on` DATETIME NULL AFTER `queue`,
	ADD COLUMN `duration_ms` DECIMAL(10,2) NULL DEFAULT NULL AFTER `processed_on`;
	
UPDATE `exf_queued_task` exf_queued_task SET `status` = 30 WHERE `status` = 10;
UPDATE `exf_queued_task` exf_queued_task SET `status` = 98 WHERE `status` = 90;

ALTER TABLE `exf_queue`
	ADD COLUMN `description` VARCHAR(400) NOT NULL DEFAULT '' AFTER `prototype_class`;
	
-- DOWN

ALTER TABLE `exf_queued_task`
	CHANGE COLUMN `owner_oid` `owner` BINARY(16) NOT NULL AFTER `task_uxon`,
	CHANGE COLUMN `queue_oid` `queue` BINARY(16) NOT NULL AFTER `parent_item_oid`,
	DROP COLUMN `channel`,
	DROP COLUMN `processed_on`,
	DROP COLUMN `duration_ms`;
	
UPDATE `exf_queued_task` exf_queued_task SET `status` = 10 WHERE `status` = 30;
UPDATE `exf_queued_task` exf_queued_task SET `status` = 90 WHERE `status` = 98;

ALTER TABLE `exf_queue`
	DROP COLUMN `description`;
	
ALTER TABLE `exf_queued_task`
	CHANGE COLUMN `queue` `queue` BINARY(16) NOT NULL AFTER `parent_item_oid`;