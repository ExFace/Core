-- UP

CREATE TABLE IF NOT EXISTS `exf_monitor_action` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `action_name` varchar(200) NOT NULL,
  `widget_name` varchar(200) DEFAULT NULL,
  `time` datetime NOT NULL,
  `date` date NOT NULL,
  `action_alias` varchar(100) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `object_oid` binary(16) DEFAULT NULL,
  `page_oid` binary(16) DEFAULT NULL,
  `user_oid` binary(16) DEFAULT NULL,
  `facade_alias` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  KEY `date-user-page` (`date`,`user_oid`,`page_oid`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- DOWN

DROP TABLE `exf_monitor_action`;