-- UP

ALTER TABLE `exf_attribute`
	DROP INDEX `object_oid`,
	DROP INDEX `related_object_oid`,
	ADD INDEX `ModelLoader queries` (`object_oid`, `related_object_oid`);
	
ALTER TABLE `exf_data_type`
	ADD INDEX `ModelLoader queries` (`app_oid`, `validation_error_oid`);
	
ALTER TABLE `exf_object`
	DROP INDEX `alias`,
	DROP INDEX `app_oid`,
	DROP INDEX `parent_object_oid`,
	DROP INDEX `data_source_oid`,
	DROP INDEX `object_alias + app_oid`,
	ADD INDEX `ModelLoader queries` (`object_alias`, `app_oid`, `data_source_oid`),
	ADD INDEX `Default listing in model editor` (`created_on`);
	
ALTER TABLE `exf_object_behaviors`
	ADD INDEX `object_oid` (`object_oid`);
	
ALTER TABLE `exf_data_source`
	ADD INDEX `ModelBuilder queries` (`oid`, `app_oid`, `default_connection_oid`, `custom_connection_oid`);
	
ALTER TABLE `exf_object_action`
	DROP INDEX `object_oid`,
	ADD INDEX `ModelLoader queries` (`alias`, `action_app_oid`);

-- DOWN

ALTER TABLE `exf_attribute`
	ADD INDEX `object_oid` (`object_oid`),
	ADD INDEX `related_object_oid` (`related_object_oid`),
	DROP INDEX `ModelLoader queries`;
	
ALTER TABLE `exf_data_type`
	DROP INDEX `ModelLoader queries`;
	
ALTER TABLE `exf_object`
	ADD INDEX `alias` (`object_alias`),
	ADD INDEX `app_oid` (`app_oid`),
	ADD INDEX `parent_object_oid` (`parent_object_oid`),
	ADD INDEX `data_source_oid` (`data_source_oid`),
	ADD INDEX `object_alias + app_oid` (`object_alias`, `app_oid`),
	DROP INDEX `ModelLoader queries`,
	DROP INDEX `Default listing in model editor`;

ALTER TABLE `exf_object_behaviors`
	DROP INDEX `object_oid`;
	
ALTER TABLE `exf_data_source`
	DROP INDEX `ModelBuilder queries`;
	
ALTER TABLE `exf_object_action`
	ADD INDEX `object_oid` (`object_oid`),
	DROP INDEX `ModelLoader queries`;
	
