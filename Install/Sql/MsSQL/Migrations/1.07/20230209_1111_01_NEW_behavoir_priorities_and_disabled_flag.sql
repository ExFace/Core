-- UP

ALTER TABLE dbo.exf_object_behaviors
	ADD [priority] INT NULL,
		[disabled_flag] TINYINT NOT NULL DEFAULT '0';
	
-- DOWN

IF COL_LENGTH('dbo.exf_object_behaviors', 'priority') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = 'exf_object_behaviors',
			@column NVARCHAR(50) = 'priority'
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

IF COL_LENGTH('dbo.exf_object_behaviors', 'disabled_flag') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = 'exf_object_behaviors',
			@column NVARCHAR(50) = 'disabled_flag'
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
