-- UP

CREATE TABLE IF NOT EXISTS `exf_task_queue` (
	`oid` binary(16) NOT NULL,
	`created_on` datetime NOT NULL,
	`modified_on` datetime NOT NULL,
	`created_by_user_oid` binary(16) DEFAULT NULL,
	`modified_by_user_oid` binary(16) DEFAULT NULL,
	`origin` VARCHAR(20) NOT NULL,
	`task_uxon` longtext NOT NULL,
	`owner` binary(16) DEFAULT NULL,
    `status` int(2) NOT NULL,
    `result` longtext DEFAULT NULL,
    `error_message` longtext DEFAULT NULL,
    `error_logid` varchar(20) DEFAULT NULL,
    `parent_item_oid` binary(16) DEFAULT NULL,
	`sync_flag` TINYINT(1) NULL DEFAULT '0',
	PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- DOWN

DROP TABLE IF EXISTS `exf_task_queue`;