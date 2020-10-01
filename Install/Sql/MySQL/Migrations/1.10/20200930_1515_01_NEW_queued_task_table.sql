-- UP

CREATE TABLE IF NOT EXISTS `exf_queued_task` (
	`oid` binary(16) NOT NULL,
	`created_on` datetime NOT NULL,
	`modified_on` datetime NOT NULL,
	`created_by_user_oid` binary(16) DEFAULT NULL,
	`modified_by_user_oid` binary(16) DEFAULT NULL,
	`producer` VARCHAR(50) NOT NULL,
	`message_id` VARCHAR(50) DEFAULT NULL,
	`task_assigned_on` datetime NOT NULL,
	`task_uxon` longtext NOT NULL,
	`owner` binary(16) NOT NULL,
    `status` int(2) NOT NULL,
	`topics` VARCHAR(500) DEFAULT NULL,
    `result` longtext DEFAULT NULL,
    `error_message` longtext DEFAULT NULL,
    `error_logid` varchar(20) DEFAULT NULL,
    `parent_item_oid` binary(16) DEFAULT NULL,
	`queue` binary(16) NOT NULL,
	PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- DOWN

DROP TABLE IF EXISTS `exf_task_queue`;