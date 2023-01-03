-- UP

CREATE TABLE IF NOT EXISTS `exf_pwa` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `icon_uri` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `start_page_oid` binary(16) NOT NULL,
  `page_template_oid` binary(16) NOT NULL,
  `alias` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `url` varchar(100) NOT NULL,
  `active_flag` tinyint NOT NULL DEFAULT '1',
  `installable_flag` tinyint NOT NULL DEFAULT '1',
  `available_offline_flag` tinyint NOT NULL DEFAULT '1',
  `available_offline_help_flag` tinyint NOT NULL DEFAULT '0',
  `available_offline_unpublished_flag` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `app+alias unique` (`alias`,`app_oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_pwa_action` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `pwa_oid` binary(16) NOT NULL,
  `description` varchar(400) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `action_alias` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `object_action_oid` binary(16) DEFAULT NULL,
  `offline_strategy` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `page_oid` binary(16) NOT NULL,
  `trigger_widget_id` varchar(400) NOT NULL,
  `trigger_widget_type` varchar(100) NOT NULL,
  `pwa_dataset_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `Unique_per_page_widget_and_PWA` (`page_oid`,`trigger_widget_id`,`pwa_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_pwa_dataset` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `pwa_oid` binary(16) NOT NULL,
  `object_oid` binary(16) NOT NULL,
  `description` varchar(400) NOT NULL,
  `data_sheet_uxon` text NOT NULL,
  `user_defined_flag` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_pwa_route` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `pwa_oid` binary(16) NOT NULL,
  `pwa_action_oid` binary(16) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `description` varchar(400) NOT NULL,
  `user_defined_flag` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `Max_1_route_per_PWA_action` (`pwa_action_oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
	
-- DOWN

DROP TABLE IF EXISTS `exf_pwa`;
DROP TABLE IF EXISTS `exf_pwa_action`;
DROP TABLE IF EXISTS `exf_pwa_route`;
DROP TABLE IF EXISTS `exf_pwa_dataset`;