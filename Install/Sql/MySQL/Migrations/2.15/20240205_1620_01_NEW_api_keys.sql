-- UP

CREATE TABLE IF NOT EXISTS `exf_user_api_key` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `user_oid` binary(16) NOT NULL,
  `key_hash` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `name` varchar(100) NOT NULL,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `Key unique` (`key_hash`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;


-- DOWN

DROP TABLE IF EXISTS `exf_user_api_key`;