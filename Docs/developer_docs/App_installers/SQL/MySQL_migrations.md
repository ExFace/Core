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

## Foreign keys and indexes

### Drop index if exists

```
set @table_name = 'my_table';
set @index_name = 'my_index';
set @var=if(
	(SELECT true 
		FROM information_schema.statistics 
		WHERE table_schema = DATABASE() 
			AND TABLE_NAME = @table_name 
			AND INDEX_NAME = @index_name
	) = true,
    CONCAT('ALTER TABLE `', @table_name, '` DROP INDEX `', @index_name, '`'),
    'SELECT CONCAT(\'Index "\', @my_index, \'" does not exist!\''
);
prepare stmt from @var;
execute stmt;
deallocate prepare stmt;
```

### Drop foreign key

```
set @table_name = 'my_table';
set @index_name = 'my_index';
/* Remove foreign key */
set @var=if(
	(SELECT true 
		FROM information_schema.TABLE_CONSTRAINTS 
		WHERE CONSTRAINT_SCHEMA = DATABASE()
		    AND TABLE_NAME        = @table_name
		    AND CONSTRAINT_NAME   = @index_name
		    AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
    ) = true,
    CONCAT('ALTER TABLE `', @table_name, '` DROP FOREIGN KEY `', @index_name, '`'),
    'SELECT CONCAT(\'Foreign key "\', @my_index, \'" does not exist!\''
);
prepare stmt from @var;
execute stmt;
deallocate prepare stmt;
/* Remove index */
set @var=if(
	(SELECT true 
		FROM information_schema.statistics 
		WHERE table_schema = DATABASE() 
			AND TABLE_NAME = @table_name 
			AND INDEX_NAME = @index_name
	) = true,
    CONCAT('ALTER TABLE `', @table_name, '` DROP INDEX `', @index_name, '`'),
    'SELECT CONCAT(\'Index "\', @my_index, \'" does not exist!\''
);
prepare stmt from @var;
execute stmt;
deallocate prepare stmt;
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