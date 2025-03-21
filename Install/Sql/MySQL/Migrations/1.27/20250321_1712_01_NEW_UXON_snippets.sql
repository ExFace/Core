-- UP

CREATE TABLE `exf_uxon_snippet` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `object_oid` binary(16) DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `app_oid` binary(16) NOT NULL,
  `alias` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uxon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uxon_schema` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prototype` varchar(200) NOT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per app` (`app_oid`,`alias`),
  KEY `object_oid` (`object_oid`) USING BTREE,
  CONSTRAINT `FK_exf_uxon_snippet_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`),
  CONSTRAINT `FK_exf_uxon_snippet_object` FOREIGN KEY (`object_oid`) REFERENCES `exf_object` (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;