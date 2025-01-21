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
-- Define the schema and table name to be dropped
DECLARE @SchemaName NVARCHAR(MAX) = 'YourSchemaName';
DECLARE @TableName NVARCHAR(MAX) = 'YourTableName';
DECLARE @FullTableName NVARCHAR(MAX) = QUOTENAME(@SchemaName) + '.' + QUOTENAME(@TableName);

-- Drop foreign key constraints
DECLARE @Sql NVARCHAR(MAX);
SELECT @Sql = STRING_AGG(CONCAT('ALTER TABLE ', QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)), '.', QUOTENAME(OBJECT_NAME(parent_object_id)), 
								' DROP CONSTRAINT ', QUOTENAME(name), ';'), ' ')
FROM sys.foreign_keys
WHERE parent_object_id = OBJECT_ID(@FullTableName);

IF @Sql IS NOT NULL
	EXEC sp_executesql @Sql;

-- Drop primary key constraints
SELECT @Sql = STRING_AGG(CONCAT('ALTER TABLE ', QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)), '.', QUOTENAME(OBJECT_NAME(parent_object_id)), 
								' DROP CONSTRAINT ', QUOTENAME(name), ';'), ' ')
FROM sys.key_constraints
WHERE parent_object_id = OBJECT_ID(@FullTableName) AND type = 'PK';

IF @Sql IS NOT NULL
	EXEC sp_executesql @Sql;

-- Drop indexes
SELECT @Sql = STRING_AGG(CONCAT('DROP INDEX ', QUOTENAME(name), ' ON ', QUOTENAME(OBJECT_SCHEMA_NAME(object_id)), '.', QUOTENAME(OBJECT_NAME(object_id)), ';'), ' ')
FROM sys.indexes
WHERE object_id = OBJECT_ID(@FullTableName) AND name IS NOT NULL AND type > 0;

IF @Sql IS NOT NULL
	EXEC sp_executesql @Sql;

-- Finally, drop the table
SET @Sql = CONCAT('DROP TABLE ', @FullTableName, ';');
EXEC sp_executesql @Sql;
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
BEGIN TRANSACTION;
BEGIN TRY
    -- Declare table, column, and schema names
    DECLARE @schema NVARCHAR(256) = '<YourSchemaName>';
    DECLARE @table NVARCHAR(256) = '<YourTableName>';
    DECLARE @column NVARCHAR(256) = '<YourColumnName>';
    DECLARE @sql NVARCHAR(MAX);

    -- Fully qualified table name
    DECLARE @qualifiedTable NVARCHAR(MAX) = QUOTENAME(@schema) + '.' + QUOTENAME(@table);

    -- Drop Default Constraints
    SELECT @sql = STRING_AGG('ALTER TABLE ' + @qualifiedTable + ' DROP CONSTRAINT ' + QUOTENAME(name), '; ')
    FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID(@schema + '.' + @table) 
      AND COL_NAME(parent_object_id, parent_column_id) = @column;

    IF @sql IS NOT NULL EXEC sp_executesql @sql;

    -- Drop Foreign Key Constraints
    SELECT @sql = STRING_AGG('ALTER TABLE ' + @qualifiedTable + ' DROP CONSTRAINT ' + QUOTENAME(name), '; ')
    FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID(@schema + '.' + @table);

    IF @sql IS NOT NULL EXEC sp_executesql @sql;

    -- Drop Indexes
    SELECT @sql = STRING_AGG('DROP INDEX ' + QUOTENAME(i.name) + ' ON ' + @qualifiedTable, '; ')
    FROM sys.indexes i
    	INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
    WHERE OBJECT_NAME(ic.object_id, DB_ID(@schema)) = @table
    	AND COL_NAME(ic.object_id, ic.column_id) = @column;

    IF @sql IS NOT NULL EXEC sp_executesql @sql;

    -- Drop the Column
    SET @sql = 'ALTER TABLE ' + @qualifiedTable + ' DROP COLUMN ' + QUOTENAME(@column);
	IF COL_LENGTH(CONCAT(@schema, '.', @table), @column) IS NOT NULL
    EXEC sp_executesql @sql;

    -- Commit transaction if all operations succeed
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    -- Rollback transaction in case of an error
    ROLLBACK TRANSACTION;

    -- Output error details
    THROW;
END CATCH;
```

### Add/Remove multiple columns

```
ALTER TABLE [dbo].[YourTableName]
	ADD 	[YourColumnName1] NVARCHAR(max) NULL,
			[YourColumnName2] NVARCHAR(max) NULL;

```

## Foreign keys

Use named foreign keys in order to be able to quickly remove them in down scripts

```
ALTER TABLE dbo.etl_file_upload
ADD CONSTRAINT FK_dbo_file_upload_file_flow_oid FOREIGN KEY (file_flow_oid) REFERENCES dbo.etl_file_flow (oid);
```

Available rules:

- `ON DELETE`
- `ON UPDATE`

Available actions for these rules:

- `NO ACTION`
- `CASCADE`
- `SET NULL`
- `SET DEFAULT`

## Indexes and constraints

### Checking for constraints in general

```
IF (OBJECT_ID('dbo.YourInexName', 'F') IS NOT NULL)
```

The second parameter is the type of the constraint:

- `C` = CHECK constraint
- `D` = DEFAULT (constraint or stand-alone)
- `F` = FOREIGN KEY constraint
- `PK` = PRIMARY KEY constraint
- `UQ` = UNIQUE constraint

Full List of types [here](http://technet.microsoft.com/en-us/library/ms190324.aspx):

Specifically for indexs, the following works too:

```
IF IndexProperty(Object_Id('dbo.YourTableName'), 'YourIndexName', 'IndexID') IS NOT NULL
```

### Add an index

```
IF IndexProperty(Object_Id('dbo.YourTableName'), 'YourIndexName', 'IndexID') IS NULL
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