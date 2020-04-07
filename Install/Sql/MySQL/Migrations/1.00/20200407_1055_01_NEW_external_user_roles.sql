-- UP

CREATE TABLE IF NOT EXISTS `exf_user_role_external` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `alias` varchar(50) NOT NULL,
  `user_role_oid` binary(16) DEFAULT NULL,
  `authenticator_class` varchar(200) DEFAULT NULL,
  `authenticator_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

ALTER TABLE `exf_user_role`
	ADD COLUMN `sync_with_external_role_oid` BINARY(16) NULL DEFAULT NULL AFTER `app_oid`;
	
-- DOWN

ALTER TABLE `exf_user_role`
	CHANGE COLUMN `alias` `alias` VARCHAR(50) NULL COLLATE 'utf8_general_ci' AFTER `name`;
	
DROP TABLE IF EXISTS `exf_user_role_external`;
