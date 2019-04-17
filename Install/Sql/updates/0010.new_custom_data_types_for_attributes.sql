ALTER TABLE `exf_attribute` ADD `custom_data_type_uxon` LONGTEXT NULL AFTER `attribute_short_description`;

ALTER TABLE `exf_data_type` CHANGE `default_widget_uxon` `default_editor_uxon` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;