-- UP

CREATE TABLE IF NOT EXISTS `exf_communication_template` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `alias` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `communication_channel_oid` binary(16) NOT NULL,
  `message_uxon` text NOT NULL,
  `object_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `app+alias` (`alias`,`app_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_communication_template`;