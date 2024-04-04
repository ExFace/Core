-- UP

ALTER TABLE `exf_attribute`
	ADD COLUMN `attribute_copyable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `attribute_editable_flag`;
	
UPDATE `exf_attribute` SET `attribute_copyable_flag` = `attribute_editable_flag`;

REPLACE INTO `exf_attribute` (`oid`, `attribute_alias`, `attribute_name`, `object_oid`, `data`, `data_properties`, `attribute_formatter`, `data_type_oid`, `default_display_order`, `default_sorter_order`, `default_sorter_dir`, `object_label_flag`, `object_uid_flag`, `attribute_readable_flag`, `attribute_writable_flag`, `attribute_hidden_flag`, `attribute_editable_flag`, `attribute_copyable_flag`, `attribute_required_flag`, `attribute_system_flag`, `attribute_sortable_flag`, `attribute_filterable_flag`, `attribute_aggregatable_flag`, `default_value`, `fixed_value`, `related_object_oid`, `related_object_special_key_attribute_oid`, `relation_cardinality`, `copy_with_related_object`, `delete_with_related_object`, `attribute_short_description`, `default_editor_uxon`, `default_display_uxon`, `custom_data_type_uxon`, `comments`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `default_aggregate_function`, `value_list_delimiter`, `attribute_type`) VALUES
(0x11ebb21c1836b84cb21c847beb4a5184, 'COPYABLEFLAG', 'Copyable', 0x32350000000000000000000000000000, 'attribute_copyable_flag', NULL, '', 0x37000000000000000000000000000000, NULL, NULL, '', 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, '1', '', NULL, NULL, '', 0, 0, 'Uncheck if this attribute must not be copied with its object. This is usefull if for attributes that represent object lifecycle data like statuses, errors, etc.', NULL, NULL, NULL, '', '2021-01-11 22:15:13', '2021-01-12 15:03:29', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ',', 'D');
	
-- DOWN

ALTER TABLE `exf_attribute`
	DROP COLUMN `attribute_copyable_flag`;