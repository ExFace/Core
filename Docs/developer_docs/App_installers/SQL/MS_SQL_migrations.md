# SQL migrations with Microsoft SQL

## Columns

Replace `{TABLE_NAME}` and `{COLUMN_NAME}` with the name of the respective table/column names. Also replace `dbo` if using a different scheme. 

### Add columns

```
IF COL_LENGTH('dbo.{COLUMN_NAME}','{TABLE_NAME}') IS NULL
ALTER TABLE "dbo"."{COLUMN_NAME}"
	ADD "{COLUMN_NAME}" INT NOT NULL;
```

### Remove columns

```
DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE dbo.{TABLE_NAME} DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('{TABLE_NAME}')
    AND c.name IN (N'{COLUMN_NAME}')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END

IF COL_LENGTH('dbo.{TABLE_NAME}','{COLUMN_NAME}') IS NOT NULL
ALTER TABLE "dbo"."{TABLE_NAME}"
	DROP COLUMN "{COLUMN_NAME}";
```