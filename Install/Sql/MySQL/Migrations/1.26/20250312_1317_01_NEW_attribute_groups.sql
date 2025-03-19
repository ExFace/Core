-- UP

CREATE TABLE IF NOT EXISTS `exf_attribute_group` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `object_oid` binary(16) NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `alias` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `app_oid` binary(16) NOT NULL,
  `description` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Name unique per app` (`name`,`app_oid`) USING BTREE,
  KEY `object_oid` (`object_oid`),
  KEY `app_oid` (`app_oid`),
  CONSTRAINT `FK_attribute_group_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_attribute_group_attributes` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `attribute_oid` binary(16) NOT NULL,
  `attribute_group_oid` binary(16) NOT NULL,
  `pos` tinyint NOT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Attribute unique per group` (`attribute_oid`,`attribute_group_oid`) USING BTREE,
  KEY `Read groups from model loader` (`attribute_group_oid`,`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

-- DOWN

-- DO NOT drop tables with potential content!