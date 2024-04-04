-- UP

ALTER TABLE `exf_queued_task`
	ADD COLUMN `scheduler_oid` BINARY(16) NULL DEFAULT NULL AFTER `duration_ms`;
	
CREATE TABLE IF NOT EXISTS `exf_customizing` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `row_oid` binary(16) NOT NULL,
  `column_name` varchar(50) NOT NULL,
  `value` varchar(200) NOT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `Ref. table cell` (`row_oid`,`column_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_scheduler` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `name` varchar(50) NOT NULL,
  `schedule` varchar(50) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `action_uxon` longtext,
  `task_uxon` longtext,
  `app_oid` binary(16) DEFAULT NULL,
  `queue_topics` varchar(50) NOT NULL,
  `first_run` datetime NOT NULL,
  `last_run` datetime DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

ALTER TABLE `exf_queued_task`
	ADD INDEX `Find duplicates` (`message_id`, `producer`, `queue_oid`, `status`),
	ADD INDEX `Scheduler` (`scheduler_oid`, `created_on`),
	ADD INDEX `Initial Views` (`created_on`, `task_assigned_on`, `owner_oid`, `queue_oid`);
	
-- DOWN

ALTER TABLE `exf_queued_task`
	DROP COLUMN `scheduler_oid`,
	DROP INDEX `Find duplicates`,
	DROP INDEX `Scheduler`,
	DROP INDEX `Initial Views`;
	
DROP TABLE IF EXISTS `exf_customizing`;

DROP TABLE IF EXISTS `exf_scheduler`;