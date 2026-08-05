-- UP

-- Check if a primary key already exists
SET @pk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exf_page'
      AND CONSTRAINT_TYPE = 'PRIMARY KEY'
);

-- If no PK exists, then add one
SET @sql := IF(@pk_exists = 0,
               'ALTER TABLE exf_page ADD PRIMARY KEY (oid);',
               'SELECT "Primary key already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN
    
-- Do not remove the primary key! After all, it was always supposed to be there!