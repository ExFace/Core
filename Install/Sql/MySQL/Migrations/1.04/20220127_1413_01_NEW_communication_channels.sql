-- UP

CREATE TABLE IF NOT EXISTS `exf_communication_channel` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `alias` varchar(100) NOT NULL,
  `descr` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `data_connection_oid` binary(16) DEFAULT NULL,
  `message_prototype` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `message_default_uxon` longtext,
  `mute_flag` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

	
-- DOWN

DROP TABLE IF EXISTS `exf_communication_channel`;