# SQL migrations with Microsoft SQL

Unfortunately writing fail-safe SQL is pretty complicated with MS SQL Server. Here are a couple of techniques for common migration scenarios, that can be easily reused.

## Tables

Replace `{TABLE_NAME}` with the name of your desired table and `dbo` if using a different schema.

### Create a table

```
IF OBJECT_ID ({TABLE_NAME}', N'U') IS NULL 
CREATE TABLE "{TABLE_NAME}" (
	"Id" BIGINT NOT NULL,
	PRIMARY KEY ("Id")
);
```

### Drop a table

```
DECLARE @SQL NVARCHAR(MAX) = N'';
SELECT @SQL += N'
ALTER TABLE ' + OBJECT_NAME(PARENT_OBJECT_ID) + ' DROP CONSTRAINT ' + OBJECT_NAME(OBJECT_ID) + ';' 
FROM SYS.OBJECTS
WHERE TYPE_DESC LIKE '%CONSTRAINT' AND OBJECT_NAME(PARENT_OBJECT_ID) IN ('{TABLE_NAME_WITHOUT_SCHEMA}');
EXECUTE(@SQL);

IF OBJECT_ID (N'{TABLE_NAME}', N'U') IS NOT NULL
DROP TABLE "{TABLE_NAME}";
```

## Columns

Replace `{TABLE_NAME}` and `{COLUMN_NAME}` with the name of the respective table/column names. Also replace `dbo` if using a different schema. 

### Add columns - nullable

```
IF COL_LENGTH('dbo.{TABLE_NAME}','{COLUMN_NAME}') IS NULL
ALTER TABLE "dbo"."{TABLE_NAME}"
	ADD "{COLUMN_NAME}" INT NULL;
```

### Add columns - not nullable, with default values

```
IF COL_LENGTH('dbo.{TABLE_NAME}','{COLUMN_NAME}') IS NULL
ALTER TABLE "dbo"."{TABLE_NAME}"
	ADD "{COLUMN_NAME}" INT NOT NULL DEFAULT 0;
```

### Add columns - not nullable, without default values

IF adding a required column without a default value, we need to provide values for already existing rows explicitly. Technically, we can add a nullable column, set values and make it not-nullable afterwards.

```
IF COL_LENGTH('dbo.{TABLE_NAME}', '{COLUMN_NAME}') IS NULL
BEGIN
	ALTER TABLE "dbo"."{TABLE_NAME}"
		ADD "{COLUMN_NAME}" BIGINT NULL

	EXEC sys.sp_executesql @query = N'UPDATE dbo.{TABLE_NAME} SET {COLUMN_NAME} = 0;'

	ALTER TABLE "dbo"."{TABLE_NAME}"
		ALTER COLUMN "{COLUMN_NAME}" BIGINT NOT NULL
END
```

### Remove columns 

When removing a column, we need to drop all related constraints first. Of course, we must check if the column really exists too!

```
IF COL_LENGTH('dbo.{TABLE_NAME}', '{COLUMN_NAME}') IS NOT NULL
BEGIN
	DECLARE @sql NVARCHAR(MAX),
			@schema NVARCHAR(50) = 'dbo',
			@table NVARCHAR(50) = '{TABLE_NAME}',
			@column NVARCHAR(50) = '{COLUMN_NAME}'
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
ALTER TABLE [dbo].[{TABLE_NAME}]
	ADD 	[{COLUMN_NAME_1}] NVARCHAR(max) NULL,
			[{COLUMN_NAME_2}] NVARCHAR(max) NULL;

```

## Data

### Initial data

```
MERGE dbo.{TABLE_NAME} with(HOLDLOCK) as target
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