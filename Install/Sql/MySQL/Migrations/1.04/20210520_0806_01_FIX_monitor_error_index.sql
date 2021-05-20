-- UP

ALTER TABLE `exf_monitor_error`
	ADD INDEX `logid` (`log_id`),
	ADD INDEX `date-user-status` (`date`, `user_oid`, `status`);
	
-- DOWN

ALTER TABLE `exf_monitor_error`
	DROP INDEX `logid`,
	DROP INDEX `date-user-status`;