ALTER TABLE `exf_object` ADD `comments` TEXT NULL AFTER `default_editor_uxon`;
ALTER TABLE `exf_object` CHANGE `short_description` `short_description` VARCHAR(400) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `exf_attribute` ADD `comments` TEXT NULL AFTER `custom_data_type_uxon`;
ALTER TABLE `exf_attribute` ADD `default_display_uxon` TEXT NULL AFTER `default_editor_uxon`;
ALTER TABLE `exf_attribute` CHANGE `attribute_short_description` `attribute_short_description` VARCHAR(400) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `exf_error` ADD `hint` VARCHAR(250) NULL AFTER `error_text`;
ALTER TABLE `exf_error` ADD `type` VARCHAR(10) NOT NULL AFTER `description`, ADD `docs_path` VARCHAR(50) NULL AFTER `type`;
UPDATE `exf_error` SET `type` = 'ERROR';
ALTER TABLE `exf_error` ADD UNIQUE `code` (`error_code`);
RENAME TABLE `exf_error` TO `exf_message`;
ALTER TABLE `exf_message` 
	CHANGE `error_code` `code` VARCHAR(8) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `error_text` `title` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

UPDATE `exf_object` SET object_alias = 'MESSAGE', data_address = 'exf_message' WHERE oid = 0x11e6c3859abc5faea3e40205857feb80;