-- UP

IF COL_LENGTH('dbo.exf_auth_policy','exf_auth_policy') IS NULL
ALTER TABLE dbo.exf_auth_policy
	ADD target_app_oid BINARY(16) NULL;
IF COL_LENGTH('dbo.exf_auth_point','exf_auth_point') IS NULL
ALTER TABLE dbo.exf_auth_point
	ADD target_app_applicable TINYINT NOT NULL DEFAULT 0;
	
IF NOT EXISTS (SELECT oid FROM dbo.exf_attribute WHERE oid = 0x11ee8f8e9630082e8f8e025041000001)
INSERT INTO dbo.exf_attribute (oid, attribute_alias, attribute_name, object_oid, data, data_properties, attribute_formatter, data_type_oid, default_display_order, default_sorter_order, default_sorter_dir, object_label_flag, object_uid_flag, attribute_readable_flag, attribute_writable_flag, attribute_hidden_flag, attribute_editable_flag, attribute_copyable_flag, attribute_required_flag, attribute_system_flag, attribute_sortable_flag, attribute_filterable_flag, attribute_aggregatable_flag, default_value, fixed_value, related_object_oid, related_object_special_key_attribute_oid, relation_cardinality, copy_with_related_object, delete_with_related_object, attribute_short_description, default_editor_uxon, default_display_uxon, custom_data_type_uxon, comments, created_on, modified_on, created_by_user_oid, modified_by_user_oid, default_aggregate_function, value_list_delimiter, attribute_type) VALUES
(0x11ee8f8e9630082e8f8e025041000001, 'TARGET_APP', 'App', 0x11ea63083a80f8c8a2e30205857feb80, 'target_app_oid', '{\"SQL_DATA_TYPE\":\"binary\"}', '', 0x11e8091315d97da6b5b5e4b318306b9a, NULL, NULL, '', 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, '', '', 0x35370000000000000000000000000000, NULL, '', 0, 0, '', NULL, NULL, NULL, '', '2023-12-20 09:48:45', '2023-12-20 10:00:27', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ',', 'D');

-- DOWN

IF COL_LENGTH('dbo.exf_auth_policy','exf_auth_policy') IS NOT NULL
ALTER TABLE dbo.exf_auth_policy
	DROP COLUMN target_app_oid;

IF COL_LENGTH('dbo.exf_auth_point', 'target_app_applicable') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = 'exf_auth_point',
			@column NVARCHAR(50) = 'target_app_applicable'
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