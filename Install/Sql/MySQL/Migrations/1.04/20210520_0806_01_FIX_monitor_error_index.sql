-- UP

ALTER TABLE `exf_monitor_error`
	ADD INDEX `logid` (`log_id`);
	
-- DOWN

ALTER TABLE `exf_monitor_error`
	DROP INDEX `logid`;