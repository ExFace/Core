-- UP

ALTER TABLE `exf_data_type`
	ADD COLUMN `default_display_uxon` LONGTEXT NULL DEFAULT NULL AFTER `default_editor_uxon`;
	
INSERT IGNORE INTO `exf_attribute` (`oid`, `attribute_alias`, `attribute_name`, `object_oid`, `data`, `data_properties`, `attribute_formatter`, `data_type_oid`, `default_display_order`, `default_sorter_order`, `default_sorter_dir`, `object_label_flag`, `object_uid_flag`, `attribute_readable_flag`, `attribute_writable_flag`, `attribute_hidden_flag`, `attribute_editable_flag`, `attribute_copyable_flag`, `attribute_required_flag`, `attribute_system_flag`, `attribute_sortable_flag`, `attribute_filterable_flag`, `attribute_aggregatable_flag`, `default_value`, `fixed_value`, `related_object_oid`, `related_object_special_key_attribute_oid`, `relation_cardinality`, `copy_with_related_object`, `delete_with_related_object`, `attribute_short_description`, `default_editor_uxon`, `default_display_uxon`, `custom_data_type_uxon`, `comments`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `default_aggregate_function`, `value_list_delimiter`, `attribute_type`) VALUES
(0x11eb85e9cccb550685e9025041000001, 'DEFAULT_DISPLAY_UXON', 'Default display', 0x32360000000000000000000000000000, 'default_display_uxon', NULL, '', 0x11e905162eeb6334b04c0205857feb80, NULL, NULL, '', 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, '', '', NULL, NULL, '', 0, 0, '', NULL, NULL, '{\"length_max\":4294967295}', '', '2021-06-17 12:50:15', '2021-06-17 12:52:24', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ',', 'D');
	
-- DOWN

ALTER TABLE `exf_data_type`
	DROP COLUMN `default_display_uxon`;