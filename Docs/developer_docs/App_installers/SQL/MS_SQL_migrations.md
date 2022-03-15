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
IF OBJECT_ID (N'{TABLE_NAME}', N'U') IS NOT NULL
DROP TABLE "{TABLE_NAME}";
```

## Columns

Replace `{TABLE_NAME}` and `{COLUMN_NAME}` with the name of the respective table/column names. Also replace `dbo` if using a different schema. 

### Add columns - nullable

```
IF COL_LENGTH('dbo.{COLUMN_NAME}','{TABLE_NAME}') IS NULL
ALTER TABLE "dbo"."{COLUMN_NAME}"
	ADD "{COLUMN_NAME}" INT NULL;
```

### Add columns - not nullable, with default values

```
IF COL_LENGTH('dbo.{COLUMN_NAME}','{TABLE_NAME}') IS NULL
ALTER TABLE "dbo"."{COLUMN_NAME}"
	ADD "{COLUMN_NAME}" INT NOT NULL DEFAULT 0;
```

### Add columns - not nullable, without default values

IF adding a required column without a default value, we need to provide values for already existing rows explicitly. Technically, we can add a nullable column, set values and make it not-nullable afterwards.

```
IF COL_LENGTH('dbo.{TABLE_NAME}', '{COLUMN_NAME}') IS NULL
BEGIN
	ALTER TABLE "dbo"."{TABLE_NAME}"
		ADD "{COLUMN_NAME}" BIGINT NULL;

	EXEC sys.sp_executesql @query = N'UPDATE dbo.{TABLE_NAME} SET {COLUMN_NAME} = 0;';

	ALTER TABLE "dbo"."{TABLE_NAME}"
		ALTER COLUMN "{COLUMN_NAME}" BIGINT NOT NULL;
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
			@column NVARCHAR(50) = '{COLUMN_NAME}';
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
	EXEC(N'ALTER TABLE ['+@schema+'].['+@table+'] DROP COLUMN ['+@column+']');
END
```