-- UP

SET @dbname = DATABASE();
SET @tablename = "exf_auth_policy";
SET @columnname = "target_app_oid";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " binary(16) NULL AFTER `target_facade_class_path`")
));
	
SET @dbname = DATABASE();
SET @tablename = "exf_auth_point";
SET @columnname = "target_app_applicable";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " TINYINT NOT NULL DEFAULT '0' AFTER `target_action_applicable`")
));
	
/* Insert a stub for the policy object via SQL because otherwise the model loader
will not be able to load the APP object, that will obviously have a reverse relation
from an object, that is not there in the DB yet - it will only get placed there
by the model installer. That comes later! */
INSERT IGNORE INTO `exf_object` (`oid`, `app_oid`, `object_name`, `object_alias`, `data_address`, `data_address_properties`, `readable_flag`, `writable_flag`, `data_source_oid`, `inherit_data_source_base_object`, `parent_object_oid`, `short_description`, `docs_path`, `default_editor_uxon`, `comments`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`) VALUES
(0x11ea63083a80f8c8a2e30205857feb80, 0x31000000000000000000000000000000, 'Authorization Policy', 'AUTHORIZATION_POLICY', 'exf_auth_policy', NULL, 1, 1, 0x32000000000000000000000000000000, 1, NULL, '', '', '{\"widget_type\":\"Dialog\",\"widgets\":[{\"widget_type\":\"WidgetGroup\",\"widgets\":[{\"attribute_alias\":\"AUTHORIZATION_POINT\",\"widget_type\":\"InputComboTable\",\"id\":\"auth_point_combo\",\"table\":{\"object_alias\":\"exface.Core.AUTHORIZATION_POINT\",\"columns\":[{\"attribute_group_alias\":\"~DEFAULT_DISPLAY\"},{\"attribute_alias\":\"POLICY_PROTOTYPE_CLASS\",\"hidden\":true},{\"attribute_alias\":\"TARGET_USER_ROLE_APPLICABLE\",\"hidden\":true},{\"attribute_alias\":\"TARGET_PAGE_GROUP_APPLICABLE\",\"hidden\":true},{\"attribute_alias\":\"TARGET_OBJECT_APPLICABLE\",\"hidden\":true},{\"attribute_alias\":\"TARGET_ACTION_APPLICABLE\",\"hidden\":true},{\"attribute_alias\":\"TARGET_APP_APPLICABLE\",\"hidden\":true},{\"attribute_alias\":\"TARGET_FACADE_APPLICABLE\",\"hidden\":true}]}},{\"attribute_alias\":\"EFFECT\"},{\"attribute_alias\":\"NAME\"},{\"attribute_alias\":\"DISABLED_FLAG\"},{\"attribute_alias\":\"APP\"},{\"attribute_alias\":\"DESCRIPTION\",\"height\":3}]},{\"widget_type\":\"WidgetGroup\",\"caption\":\"Targets\",\"widgets\":[{\"attribute_alias\":\"TARGET_USER_ROLE\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_USER_ROLE_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_PAGE_GROUP\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_PAGE_GROUP_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_OBJECT\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_OBJECT_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_ACTION_MODEL\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_ACTION_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_ACTION_PROTOTYPE\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_ACTION_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_APP\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_APP_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}},{\"attribute_alias\":\"TARGET_FACADE\",\"disabled_if\":{\"operator\":\"AND\",\"conditions\":[{\"value_left\":\"=auth_point_combo!TARGET_FACADE_APPLICABLE\",\"comparator\":\"==\",\"value_right\":0}]}}]},{\"widget_type\":\"WidgetGroup\",\"caption\":\"Additional conditions\",\"width\":\"max\",\"height\":\"max\",\"widgets\":[{\"attribute_alias\":\"CONDITION_UXON\",\"widget_type\":\"InputUxon\",\"root_prototype\":\"=auth_point_combo!POLICY_PROTOTYPE_CLASS\",\"height\":\"100%\",\"width\":\"max\",\"hide_caption\":true}]}]}', '', '2020-03-10 19:49:17', '2023-12-21 09:01:39', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000);

INSERT IGNORE INTO `exf_attribute` (`oid`, `attribute_alias`, `attribute_name`, `object_oid`, `data`, `data_properties`, `attribute_formatter`, `data_type_oid`, `default_display_order`, `default_sorter_order`, `default_sorter_dir`, `object_label_flag`, `object_uid_flag`, `attribute_readable_flag`, `attribute_writable_flag`, `attribute_hidden_flag`, `attribute_editable_flag`, `attribute_copyable_flag`, `attribute_required_flag`, `attribute_system_flag`, `attribute_sortable_flag`, `attribute_filterable_flag`, `attribute_aggregatable_flag`, `default_value`, `fixed_value`, `related_object_oid`, `related_object_special_key_attribute_oid`, `relation_cardinality`, `copy_with_related_object`, `delete_with_related_object`, `attribute_short_description`, `default_editor_uxon`, `default_display_uxon`, `custom_data_type_uxon`, `comments`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `default_aggregate_function`, `value_list_delimiter`, `attribute_type`) VALUES
(0x11ee8f8e9630082e8f8e025041000001, 'TARGET_APP', 'Only for app', 0x11ea63083a80f8c8a2e30205857feb80, 'target_app_oid', '{\"SQL_DATA_TYPE\":\"binary\"}', '', 0x11e8091315d97da6b5b5e4b318306b9a, NULL, NULL, '', 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, '', '', 0x35370000000000000000000000000000, NULL, '', 0, 0, '', NULL, NULL, NULL, '', '2023-12-20 09:48:45', '2023-12-20 10:00:27', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ',', 'D');

-- DOWN

ALTER TABLE `exf_auth_policy`
	DROP COLUMN `target_app_oid`;
ALTER TABLE `exf_auth_point`
	DROP COLUMN `target_app_applicable`;