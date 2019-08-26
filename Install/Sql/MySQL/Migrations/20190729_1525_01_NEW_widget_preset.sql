-- UP
DROP TABLE IF EXISTS `exf_widget_preset`;
CREATE TABLE IF NOT EXISTS `exf_widget_preset` (
  `oid` binary(16) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `name` varchar(250) NOT NULL,
  `description` longtext,
  `uxon` longtext NOT NULL,
  `wrap_path_in_preset` varchar(255) DEFAULT NULL,
  `widget` varchar(200) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  KEY `widget` (`widget`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `exf_widget_preset` ADD `inherit_data_source_base_object` TINYINT(1) NOT NULL DEFAULT '1' AFTER `app_oid`;

-- DOWN
DROP TABLE `exf_widget_preset`;