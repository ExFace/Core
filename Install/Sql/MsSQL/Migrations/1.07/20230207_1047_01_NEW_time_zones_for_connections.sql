-- UP

INSERT INTO dbo.[exf_data_type] (
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
	[modified_by_user_oid]
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
		
ALTER TABLE dbo.[exf_data_connection]
	ADD [time_zone] NVARCHAR(50) NULL;

UPDATE dbo.[exf_data_connection] 
	SET data_connector_config = JSON_MODIFY(data_connector_config, '$.filter_context', JSON_QUERY(filter_context_uxon)) 
	WHERE filter_context_UXON IS NOT NULL 
		AND filter_context_uxon <> '';
	
IF COL_LENGTH('dbo.exf_data_connection', 'filter_context_uxon') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = 'exf_data_connection',
			@column NVARCHAR(50) = 'filter_context_uxon'
	/* DROP default constraints	*/
	WHILE 1=1
	BEGIN
		SELECT TOP 1 @sql = N'ALTER TABLE '+@schema+'.'+@table+' DROP CONSTRAINT ['+dc.NAME+N']'
			FROM sys.default_constraints dc
				JOIN sys.columns c ON c.default_object_id = dc.object_id
			WHERE 
				dc.parent_object_id = OBJECT_ID(@table)
				AND c.name = @column
		IF @@ROWCOUNT = 0 BREAK
		EXEC (@sql)
	END
	/* DROP foreign keys */
	WHILE 1=1
	BEGIN
		SELECT TOP 1 @sql = N'ALTER TABLE '+@schema+'.'+@table+' DROP CONSTRAINT ['+fk.NAME+N']'
			FROM sys.foreign_keys fk
				JOIN sys.foreign_key_columns fk_cols ON fk_cols.constraint_object_id = fk.object_id
			WHERE 
				fk.parent_object_id = OBJECT_ID(@table)
				AND COL_NAME(fk.parent_object_id, fk_cols.parent_column_id) = @column
		IF @@ROWCOUNT = 0 BREAK
		EXEC (@sql)
	END
	/* DROP column */
	EXEC(N'ALTER TABLE ['+@schema+'].['+@table+'] DROP COLUMN ['+@column+']')
END
	
-- DOWN

ALTER TABLE dbo.[exf_data_connection]
	ADD [filter_context_uxon] NVARCHAR(max) NULL;
	
ALTER TABLE dbo.[exf_data_connection]
	DROP COLUMN [time_zone];