-- UP

DROP TABLE IF EXISTS `exf_page`;
CREATE TABLE IF NOT EXISTS `exf_page` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `page_template_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `intro` varchar(200) DEFAULT NULL,
  `content` longtext,
  `parent_oid` binary(16) DEFAULT NULL,
  `menu_index` int(11) NOT NULL DEFAULT '0',
  `menu_visible` tinyint(1) NOT NULL DEFAULT '1',
  `default_menu_parent_alias` varchar(100) DEFAULT NULL,
  `default_menu_parent_oid` binary(16) DEFAULT NULL,
  `default_menu_index` int(11) DEFAULT NULL,
  `replace_page_oid` binary(16) DEFAULT NULL,
  `auto_update_with_app` tinyint(1) NOT NULL DEFAULT '1',
  `published` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_page_group` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `descr` varchar(200) DEFAULT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_page_group_pages` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `page_oid` binary(16) NOT NULL,
  `page_group_oid` binary(16) NOT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_page`;
DROP TABLE IF EXISTS `exf_page_group`;
DROP TABLE IF EXISTS `exf_page_group_pages`;
