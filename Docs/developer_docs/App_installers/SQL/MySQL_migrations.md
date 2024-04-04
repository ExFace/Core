# SQL migrations on MySQL and MariaDB

## Columns

### Add column if not exists

```
SELECT count(*)
	INTO @exist
FROM information_schema.columns
	WHERE table_schema = DATABASE()
		AND COLUMN_NAME LIKE '%mycol%'
		AND table_name = 'mytable' LIMIT 1;

SET @query = IF(
	@exist <= 0, 
	'ALTER TABLE `mytable` ADD COLUMN (
        `mycolumn` tinyint(1) NOT NULL DEFAULT 0
    )',
	'select \'Column Exists\' status'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

## Conditional statements (IFs)

### Checking engine version

```
SET @version_check := IF(
	SUBSTRING_INDEX(VERSION(), '.', 3) > IF(
		POSITION('-MariaDB' IN VERSION()) = 0, 
		'5.7.8', 
		'10.5.7'
	), 
	1, 
	0
);
SET @sqlstmt := IF(
	@version_check>0,
	'DO SOMETHING, 
	'select ''Not available in current DB version''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```