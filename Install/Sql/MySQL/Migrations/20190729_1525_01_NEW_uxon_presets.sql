-- UP
DROP TABLE IF EXISTS `exf_uxon_preset`;
CREATE TABLE `exf_uxon_preset` (
  `oid` binary(16) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `name` varchar(250) NOT NULL,
  `description` longtext,
  `uxon` longtext NOT NULL,
  `wrap_path_in_preset` varchar(255) DEFAULT NULL,
  `prototype` varchar(200) DEFAULT NULL,
  `uxon_schema` varchar(20) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  KEY `prototype` (`prototype`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- DOWN
DROP TABLE `exf_uxon_preset`;