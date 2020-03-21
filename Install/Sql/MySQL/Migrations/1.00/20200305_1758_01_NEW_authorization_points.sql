-- UP

CREATE TABLE IF NOT EXISTS `exf_auth_point` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `alias` varchar(50) NOT NULL,
  `descr` varchar(200) DEFAULT NULL,
  `app_oid` binary(16) NOT NULL,
  `default_effect` CHAR(1) NOT NULL DEFAULT 'P',
  `combining_algorithm` varchar(30) NOT NULL,
  `active_flag` tinyint(1) NOT NULL DEFAULT '1',
  `target_user_role_applicable` tinyint(1) NOT NULL DEFAULT '0',
  `target_page_group_applicable` tinyint(1) NOT NULL DEFAULT '0',
  `target_facade_applicable` tinyint(1) NOT NULL DEFAULT '0',
  `target_object_applicable` tinyint(1) NOT NULL DEFAULT '0',
  `target_action_applicable` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_auth_point`;
