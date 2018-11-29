ALTER TABLE `exf_attribute`
	CHANGE COLUMN `default_display_uxon` `default_display_uxon` LONGTEXT NULL AFTER `default_editor_uxon`;
	
ALTER TABLE `exf_message`
	CHANGE COLUMN `title` `title` VARCHAR(250) NOT NULL AFTER `code`;

UPDATE exf_attribute set default_editor_uxon = null where default_editor_uxon = '{}';
UPDATE exf_attribute set default_display_uxon = null where default_display_uxon = '{}';
UPDATE exf_attribute set custom_data_type_uxon = null where custom_data_type_uxon = '{}';
UPDATE exf_attribute set data_properties = null where data_properties = '{}';

UPDATE exf_object set default_editor_uxon = null where default_editor_uxon = '{}';
UPDATE exf_object set data_address_properties = null where data_address_properties = '{}';

UPDATE exf_data_type set config_uxon = null where config_uxon = '{}';
UPDATE exf_data_type set default_editor_uxon = null where default_editor_uxon = '{}';

UPDATE exf_object_action set config_uxon = null where config_uxon = '{}';

UPDATE exf_object_behaviors set config_uxon = null where config_uxon = '{}';