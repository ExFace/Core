-- UP

CREATE TABLE IF NOT EXISTS `exf_user_authenticator` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `authenticator_id` varchar(100) NOT NULL,
  `user_oid` binary(16) NOT NULL,
  `authenticator_username` varchar(100) NOT NULL DEFAULT '',
  `disabled_flag` int(1) NOT NULL DEFAULT '0',
  `last_authenticated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_user_authenticator`;
	