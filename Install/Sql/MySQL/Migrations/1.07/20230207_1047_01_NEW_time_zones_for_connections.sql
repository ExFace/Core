-- UP

INSERT IGNORE INTO `exf_data_type` (
	`oid`,
	`data_type_alias`,
	`app_oid`,
	`name`,
	`prototype`,
	`config_uxon`,
	`default_editor_uxon`,
	`default_display_uxon`,
	`validation_error_oid`,
	`short_description`,
	`created_on`,
	`modified_on`,
	`created_by_user_oid`,
	`modified_by_user_oid`
) VALUES (
	0x11EDA0D30920B602A0D3025041000001,
	'TimeZone',
	0x31000000000000000000000000000000,
	'Time zone',
	'exface/Core/DataTypes/TimeZoneDataType.php',
	NULL,
	'{"widget_type":"InputSelect"}',
	NULL,
	NULL,
	'',
	'2023-02-07 11:34:43',
	'2023-02-07 11:35:21',
	0x31000000000000000000000000000000,
	0x31000000000000000000000000000000
);

UPDATE exf_data_connection 
	SET data_connector_config = JSON_SET(data_connector_config, '$.filter_context', CAST(filter_context_uxon AS JSON)) 
	WHERE filter_context_UXON IS NOT NULL 
		AND filter_context_uxon <> '';
		
ALTER TABLE `exf_data_connection`
	ADD COLUMN `time_zone` VARCHAR(50) NULL DEFAULT NULL AFTER `data_connector_config`,
	DROP COLUMN `filter_context_uxon`;
	
-- DOWN

ALTER TABLE `exf_data_connection`
	ADD COLUMN `filter_context` TEXT NULL DEFAULT NULL AFTER `data_connector_config`;
	
UPDATE exf_data_connection 
	SET filter_context = JSON_EXTRACT(data_connector_config, '$.filter_context')
	WHERE 
		data_connector_config IS NOT NULL 
		AND JSON_CONTAINS(data_connector_config, '$.filter_context');
		
ALTER TABLE `exf_data_connection`
	DROP COLUMN `time_zone`;

