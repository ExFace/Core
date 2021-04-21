-- UP

ALTER TABLE `exf_monitor_error`
	ADD COLUMN `request_id` VARCHAR(50) NULL DEFAULT '' AFTER `log_id`,
	ADD COLUMN `comment` TEXT NULL AFTER `action_oid`,
	ADD COLUMN `ticket_no` VARCHAR(20) NULL DEFAULT NULL AFTER `comment`;
	
-- DOWN

ALTER TABLE `exf_monitor_error`
	DROP COLUMN `request_id`,
	DROP COLUMN `comment`,
	DROP COLUMN `ticket_no`;