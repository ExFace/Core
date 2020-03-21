-- UP

CREATE TABLE IF NOT EXISTS `exf_auth_policy` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `descr` varchar(200) DEFAULT '',
  `effect` varchar(5) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `auth_point_oid` binary(16) NOT NULL,
  `target_page_group_oid` binary(16) DEFAULT NULL,
  `target_user_role_oid` binary(16) DEFAULT NULL,
  `target_object_oid` binary(16) DEFAULT NULL,
  `target_action_selector` varchar(50) DEFAULT '',
  `condition_uxon` longtext,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_auth_policy`;
