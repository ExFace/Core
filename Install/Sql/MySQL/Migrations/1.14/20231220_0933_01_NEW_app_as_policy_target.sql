-- UP

ALTER TABLE `exf_auth_policy`
	ADD COLUMN `target_app_oid` BINARY(16) NULL DEFAULT NULL AFTER `target_facade_class_path`;
ALTER TABLE `exf_auth_point`
	ADD COLUMN `target_app_applicable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `target_action_applicable`;
	
INSERT IGNORE INTO `exf_attribute` (`oid`, `attribute_alias`, `attribute_name`, `object_oid`, `data`, `data_properties`, `attribute_formatter`, `data_type_oid`, `default_display_order`, `default_sorter_order`, `default_sorter_dir`, `object_label_flag`, `object_uid_flag`, `attribute_readable_flag`, `attribute_writable_flag`, `attribute_hidden_flag`, `attribute_editable_flag`, `attribute_copyable_flag`, `attribute_required_flag`, `attribute_system_flag`, `attribute_sortable_flag`, `attribute_filterable_flag`, `attribute_aggregatable_flag`, `default_value`, `fixed_value`, `related_object_oid`, `related_object_special_key_attribute_oid`, `relation_cardinality`, `copy_with_related_object`, `delete_with_related_object`, `attribute_short_description`, `default_editor_uxon`, `default_display_uxon`, `custom_data_type_uxon`, `comments`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `default_aggregate_function`, `value_list_delimiter`, `attribute_type`) VALUES
(0x11ee8f8e9630082e8f8e025041000001, 'TARGET_APP', 'Only for app', 0x11ea63083a80f8c8a2e30205857feb80, 'target_app_oid', '{\"SQL_DATA_TYPE\":\"binary\"}', '', 0x11e8091315d97da6b5b5e4b318306b9a, NULL, NULL, '', 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, '', '', 0x35370000000000000000000000000000, NULL, '', 0, 0, '', NULL, NULL, NULL, '', '2023-12-20 09:48:45', '2023-12-20 10:00:27', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ',', 'D');

-- DOWN

ALTER TABLE `exf_auth_policy`
	DROP COLUMN `target_app_oid`;
ALTER TABLE `exf_auth_point`
	DROP COLUMN `target_app_applicable`;