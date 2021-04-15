-- UP

CREATE TABLE IF NOT EXISTS `exf_monitor_error` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `log_id` varchar(10) NOT NULL,
  `error_level` varchar(20) NOT NULL,
  `error_widget` longtext NOT NULL,
  `message` longtext NOT NULL,
  `date` date NOT NULL,
  `status` int(2) NOT NULL,
  `user_oid` binary(16) DEFAULT NULL,
  `action_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  KEY `date-user-status` (`date`,`user_oid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- DOWN

DROP TABLE `exf_monitor_error`;