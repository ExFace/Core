-- UP

CREATE TABLE IF NOT EXISTS `exf_queue` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `alias` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `prototype_class` varchar(200) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `allow_multi_queue_handling` tinyint(1) NOT NULL DEFAULT '0',
  `config_uxon` longtext,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE `exf_queue`;