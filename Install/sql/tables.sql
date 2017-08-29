SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -------------------------------------------------------------

CREATE TABLE `exf_app` (
  `oid` binary(16) NOT NULL,
  `app_alias` varchar(128) NOT NULL,
  `app_name` varchar(256) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `exf_app`
  ADD PRIMARY KEY (`oid`),
  ADD UNIQUE KEY `app_alias` (`app_alias`);
  
-- -------------------------------------------------------------  
  
CREATE TABLE `exf_attribute` (
  `oid` binary(16) NOT NULL,
  `attribute_alias` varchar(100) NOT NULL,
  `attribute_name` varchar(200) NOT NULL,
  `object_oid` binary(16) NOT NULL,
  `data` text NOT NULL COMMENT 'Data source field holding this attribute (e.g. SELECT-statement in SQL)',
  `data_properties` text COMMENT 'JSON object with data source specific properties',
  `attribute_formatter` varchar(200) DEFAULT NULL,
  `data_type_oid` binary(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `default_display_order` int(5) DEFAULT NULL,
  `default_sorter_order` int(3) DEFAULT NULL,
  `default_sorter_dir` varchar(4) DEFAULT NULL,
  `object_label_flag` tinyint(1) NOT NULL DEFAULT '0',
  `object_uid_flag` tinyint(1) NOT NULL DEFAULT '0',
  `attribute_hidden_flag` tinyint(1) NOT NULL DEFAULT '0',
  `attribute_editable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `attribute_required_flag` tinyint(1) NOT NULL DEFAULT '0',
  `attribute_system_flag` tinyint(1) NOT NULL DEFAULT '0',
  `attribute_sortable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `attribute_filterable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `attribute_aggregatable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `default_value` text,
  `fixed_value` text,
  `related_object_oid` binary(16) DEFAULT NULL,
  `related_object_special_key_attribute_oid` binary(16) DEFAULT NULL COMMENT 'Optional possibility to explicitly define, which attribute of the related object is referenced. Defaults to the UID if empty.',
  `attribute_short_description` varchar(250) DEFAULT NULL,
  `attribute_long_description` text,
  `default_editor_uxon` text COMMENT 'UXON for an editor widget to be used per default for this attribute',
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `default_aggregate_function` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `exf_attribute`
  ADD PRIMARY KEY (`oid`),
  ADD KEY `object_oid` (`object_oid`),
  ADD KEY `related_object_oid` (`related_object_oid`);
