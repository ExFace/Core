-- UP

CREATE TABLE IF NOT EXISTS `exf_pwa_build` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `pwa_oid` binary(16) NOT NULL,
  `filename` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `size` int NOT NULL,
  `content` longtext NOT NULL,
  `mimetype` varchar(100) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

ALTER TABLE `exf_pwa`
	ADD COLUMN `generated_on` DATETIME NULL AFTER `available_offline_unpublished_flag`,
	ADD COLUMN `regenerate_after` DATETIME NULL AFTER `generated_on`;

UPDATE exf_pwa SET generated_on = modified_on;
	
-- DOWN

ALTER TABLE `exf_pwa`
	DROP COLUMN `generated_on`,
	DROP COLUMN `regenerate_after`;
	
DROP TABLE `exf_pwa_build`;