-- UP

DROP TABLE IF EXISTS `exf_permalink`; 

CREATE TABLE `exf_permalink` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `object_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `description` varchar(400) DEFAULT NULL,
  `prototype_file` varchar(200) DEFAULT NULL,
  `config_uxon` longtext NOT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique` (`alias`),
  CONSTRAINT `FK_permalink_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `FK_permalink_object` FOREIGN KEY (`object_oid`) REFERENCES `exf_object` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

-- DOWN