-- UP

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
  `default_menu_position` varchar(100) DEFAULT NULL,
  `replace_page_oid` binary(16) DEFAULT NULL,
  `replace_page_alias` varchar(100) DEFAULT NULL,
  `auto_update_disabled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- DOWN

DROP TABLE IF EXISTS `exf_page`;