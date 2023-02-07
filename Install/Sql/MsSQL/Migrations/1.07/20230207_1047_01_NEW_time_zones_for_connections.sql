-- UP

INSERT INTO `exf_data_type` (
	[oid],
	[data_type_alias],
	[app_oid],
	[name],
	[prototype],
	[config_uxon],
	[default_editor_uxon],
	[default_display_uxon],
	[validation_error_oid],
	[short_description],
	[created_on],
	[modified_on],
	[created_by_user_oid],
	[modified_by_user_oid`
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
		
ALTER TABLE `exf_data_connection`
	ADD [time_zone] NVARCHAR(50) NULL,
	DROP COLUMN [filter_context_uxon];

UPDATE exf_data_connection 
	SET data_connector_config = JSON_MODIFY(data_connector_config, '$.filter_context', JSON_QUERY(filter_context_uxon)) 
	WHERE filter_context_UXON IS NOT NULL 
		AND filter_context_uxon <> '';
	
-- DOWN

