# SQL migrations with Microsoft SQL

Unfortunately writing fail-safe SQL is pretty complicated with MS SQL Server. Here are a couple of techniques for common migration scenarios, that can be easily reused.

## Tables

Replace `YourTableName` with the name of your desired table and `dbo` if using a different schema.

### Create a table

```
IF OBJECT_ID ('YourTableName', N'U') IS NULL 
CREATE TABLE "YourTableName" (
	"Id" BIGINT NOT NULL,
	PRIMARY KEY ("Id")
);
```

### Drop a table

```
DECLARE @table NVARCHAR(max) = 'YourTableName';
DECLARE @schema NVARCHAR(max) = 'dbo';
DECLARE @stmt NVARCHAR(max);

IF OBJECT_ID (CONCAT(@schema, '.', @table), N'U') IS NOT NULL
BEGIN
	-- STEP1: Remove foreign keys to this table
	-- Cursor to generate ALTER TABLE DROP CONSTRAINT statements  
	DECLARE cur CURSOR FOR
		SELECT 'ALTER TABLE ' + OBJECT_SCHEMA_NAME(parent_object_id) + '.' + OBJECT_NAME(parent_object_id) + ' DROP CONSTRAINT ' + name
		FROM sys.foreign_keys 
		WHERE OBJECT_SCHEMA_NAME(referenced_object_id) = @schema 
			AND OBJECT_NAME(referenced_object_id) = @table;
 
   OPEN cur;
   FETCH cur INTO @stmt;
	-- Drop each found foreign key constraint 
	WHILE @@FETCH_STATUS = 0
		BEGIN
			EXEC (@stmt);
			FETCH cur INTO @stmt;
		END
	CLOSE cur;
	DEALLOCATE cur;
	
	-- STEP2: remove constraints inside this table
	SELECT @stmt = '';
	SELECT @stmt += N'
ALTER TABLE ' + OBJECT_NAME(parent_object_id) + ' DROP CONSTRAINT ' + OBJECT_NAME(object_id) + ';' 
	FROM SYS.OBJECTS
	WHERE TYPE_DESC LIKE '%CONSTRAINT' AND OBJECT_NAME(parent_object_id) = @table AND SCHEMA_NAME(schema_id) = @schema;
	EXEC(@stmt);

	-- FINALLY drop the table itself
	DROP TABLE CONCAT(@schema, '.', @table);
END
```

## Columns

Replace `YourTableName` and `YourColumnName` with the name of the respective table/column names. Also replace `dbo` if using a different schema. 

### Add columns - nullable

```
IF COL_LENGTH('dbo.YourTableName','YourColumnName') IS NULL
ALTER TABLE "dbo"."YourTableName"
	ADD "YourColumnName" INT NULL;
```

### Add columns - not nullable, with default values

```
IF COL_LENGTH('dbo.YourTableName','YourColumnName') IS NULL
ALTER TABLE "dbo"."YourTableName"
	ADD "YourColumnName" INT NOT NULL DEFAULT 0;
```

### Add columns - not nullable, without default values

IF adding a required column without a default value, we need to provide values for already existing rows explicitly. Technically, we can add a nullable column, set values and make it not-nullable afterwards.

```
IF COL_LENGTH('dbo.YourTableName', 'YourColumnName') IS NULL
BEGIN
	ALTER TABLE "dbo"."YourTableName"
		ADD "YourColumnName" BIGINT NULL

	EXEC sys.sp_executesql @query = N'UPDATE dbo.YourTableName SET YourColumnName = 0;'

	ALTER TABLE "dbo"."YourTableName"
		ALTER COLUMN "YourColumnName" BIGINT NOT NULL
END
```

**NOTE:** The `EXEC` in the middle makes sure, the `ALTER TABLE` changes are applied __before__ the `UPDATE`. If the whole script is not placed in `BEGIN/END`, you can also put a `GO` in the middle to force changes to be applied.

```
IF COL_LENGTH('dbo.my_table','col1') IS NOT NULL
EXEC sp_rename 'dbo.my_table.col1', 'col2', 'COLUMN';
GO
ALTER TABLE [dbo].[my_table] ALTER COLUMN [col2] nvarchar(max) NULL;
```

### Remove columns 

When removing a column, we need to drop all related constraints first. Of course, we must check if the column really exists too!

```
IF COL_LENGTH('dbo.YourTableName', 'YourColumnName') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = 'YourTableName',
			@column NVARCHAR(50) = 'YourColumnName'
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
```

### Add/Remove multiple columns

```
ALTER TABLE [dbo].[YourTableName]
	ADD 	[YourColumnName1] NVARCHAR(max) NULL,
			[YourColumnName2] NVARCHAR(max) NULL;

```

## Indexes

### Add an index

```
If IndexProperty(Object_Id('dbo.YourTableName'), 'YourIndexName', 'IndexID') IS NULL
CREATE INDEX [YourIndexName] ON [dbo].[YourTableName] (col1, col2);
```

### Remove an index

```
DROP INDEX IF EXISTS [YourIndexName] ON [dbo].[YourTableName];
```

## Data

### Initial data

```
MERGE dbo.YourTableName with(HOLDLOCK) as target
	USING (VALUES 
		(1001, 'InitDB', 'InitDB', GETDATE(), GETDATE(), 'Name1'),
		(1002, 'InitDB', 'InitDB', GETDATE(), GETDATE(), 'Name2')
	) AS source ("Id", "ModifiedBy", "CreatedBy", "ModifiedOn", "CreatedOn", "Name")
	ON target.Id = source.Id 
WHEN MATCHED THEN
    UPDATE
    SET 	Name = source.Name,
    	  	ModifiedBy = 'InitDB',
    	  	ModifiedOn = GETDATE()
WHEN NOT MATCHED THEN
    INSERT ("Id", "ModifiedBy", "CreatedBy", "ModifiedOn", "CreatedOn", "Name")
    VALUES ("Id", "ModifiedBy", "CreatedBy", "ModifiedOn", "CreatedOn", "Name");

```