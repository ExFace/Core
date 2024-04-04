-- UP

CREATE TABLE IF NOT EXISTS `exf_notification` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `user_oid` binary(16) NOT NULL,
  `title` varchar(200) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `widget_uxon` longtext NOT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  KEY `User` (`user_oid`,`created_on`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF NOT EXISTS `exf_notification`;