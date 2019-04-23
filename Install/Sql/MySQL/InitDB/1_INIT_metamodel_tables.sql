CREATE TABLE IF NOT EXISTS `exf_app` (
  `oid` binary(16) NOT NULL,
  `app_alias` varchar(128) NOT NULL,
  `app_name` varchar(256) NOT NULL,
  `default_language_code` varchar(10) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `app_alias` (`app_alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_attribute` (
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
  `attribute_readable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `attribute_writable_flag` tinyint(1) NOT NULL DEFAULT '1',
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
  `relation_cardinality` varchar(2) NOT NULL DEFAULT '',
  `copy_with_related_object` tinyint(1) DEFAULT NULL,
  `delete_with_related_object` tinyint(1) DEFAULT NULL,
  `attribute_short_description` varchar(400) DEFAULT NULL,
  `default_editor_uxon` longtext,
  `default_display_uxon` text,
  `custom_data_type_uxon` longtext,
  `comments` text,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `default_aggregate_function` varchar(50) DEFAULT NULL,
  `value_list_delimiter` varchar(3) NOT NULL DEFAULT ',',
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per object` (`object_oid`,`attribute_alias`),
  KEY `object_oid` (`object_oid`),
  KEY `related_object_oid` (`related_object_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_data_connection` (
  `oid` binary(16) NOT NULL,
  `alias` varchar(128) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `name` varchar(64) NOT NULL,
  `data_connector` varchar(128) NOT NULL,
  `data_connector_config` text COMMENT 'JSON object with connector options to be used in this particular connection (user name, password, etc.)',
  `read_only_flag` tinyint(1) NOT NULL DEFAULT '0',
  `filter_context_uxon` varchar(250) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per app` (`alias`,`app_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_data_connection_credentials` (
  `oid` binary(16) NOT NULL,
  `data_connection_oid` binary(16) NOT NULL,
  `user_credentials_oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_data_source` (
  `oid` binary(16) NOT NULL,
  `name` varchar(32) NOT NULL,
  `alias` varchar(32) NOT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `custom_connection_oid` binary(16) DEFAULT NULL,
  `default_connection_oid` binary(16) DEFAULT NULL,
  `custom_query_builder` varchar(128) DEFAULT NULL,
  `default_query_builder` varchar(128) NOT NULL,
  `base_object_oid` binary(16) DEFAULT NULL COMMENT 'Reference to an object, that all objects of this data source should inherit from',
  `readable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `writable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per app` (`app_oid`,`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_data_type` (
  `oid` binary(16) NOT NULL,
  `data_type_alias` varchar(50) NOT NULL,
  `app_oid` binary(16) NOT NULL,
  `name` varchar(64) NOT NULL,
  `prototype` varchar(128) NOT NULL,
  `config_uxon` longtext,
  `default_editor_uxon` longtext,
  `validation_error_oid` binary(16) DEFAULT NULL,
  `short_description` varchar(250) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per app` (`app_oid`,`data_type_alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_message` (
  `oid` binary(16) NOT NULL,
  `app_oid` binary(16) NOT NULL,
  `code` varchar(8) NOT NULL,
  `title` varchar(250) NOT NULL,
  `hint` varchar(200) DEFAULT NULL,
  `description` longtext,
  `type` varchar(10) NOT NULL,
  `docs_path` varchar(200) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_object` (
  `oid` binary(16) NOT NULL,
  `app_oid` binary(16) NOT NULL,
  `object_name` varchar(200) NOT NULL,
  `object_alias` varchar(100) NOT NULL,
  `data_address` text COMMENT 'Where the object is located in the data source (e.g. table in SQL)',
  `data_address_properties` text COMMENT 'Data source specific location properties',
  `readable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `writable_flag` tinyint(1) NOT NULL DEFAULT '1',
  `data_source_oid` binary(16) DEFAULT NULL,
  `parent_object_oid` binary(16) DEFAULT NULL COMMENT 'Reference to the object, that is extended from',
  `short_description` varchar(400) DEFAULT NULL,
  `docs_path` varchar(200) DEFAULT NULL,
  `default_editor_uxon` text COMMENT 'UXON widget description',
  `comments` text,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `alias+app_oid` (`object_alias`,`app_oid`),
  KEY `alias` (`object_alias`),
  KEY `app_oid` (`app_oid`),
  KEY `parent_object_oid` (`parent_object_oid`),
  KEY `data_source_oid` (`data_source_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_object_action` (
  `oid` binary(16) NOT NULL,
  `object_oid` binary(16) NOT NULL,
  `action` varchar(128) NOT NULL,
  `alias` varchar(128) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `short_description` text,
  `docs_path` varchar(200) DEFAULT NULL,
  `config_uxon` longtext,
  `action_app_oid` binary(16) NOT NULL,
  `use_in_object_basket_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `Alias unique per app` (`action_app_oid`,`alias`),
  KEY `object_oid` (`object_oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_object_behaviors` (
  `oid` binary(16) NOT NULL,
  `object_oid` binary(16) NOT NULL,
  `name` varchar(100) NOT NULL,
  `behavior` varchar(256) NOT NULL,
  `behavior_app_oid` binary(16) NOT NULL,
  `config_uxon` longtext,
  `description` text,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_user` (
  `oid` binary(16) NOT NULL,
  `first_name` varchar(64) DEFAULT NULL,
  `last_name` varchar(64) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `locale` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `exf_user_credentials` (
  `oid` binary(16) NOT NULL,
  `user_oid` binary(16) NOT NULL,
  `name` varchar(128) NOT NULL,
  `data_connector_config` text NOT NULL COMMENT 'UXON object with connector options that will override the default connector config if this user is logged on',
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;