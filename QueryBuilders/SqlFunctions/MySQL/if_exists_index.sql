CREATE PROCEDURE `if_exists_index`(
    IN i_table_name VARCHAR(128),
    IN i_index_name VARCHAR(128),
    IN i_query VARCHAR(21845)
)
BEGIN
	SET @tableName = i_table_name;
	SET @indexName = i_index_name;
	SET @indexExists = 0;
	
	SELECT 
	    1
	INTO @indexExists FROM
	    INFORMATION_SCHEMA.STATISTICS
	WHERE
	    TABLE_NAME = @tableName
	        AND INDEX_NAME = @indexName;
	
	IF @indexExists THEN
	    PREPARE stmt FROM @i_query;
	    EXECUTE stmt;
	    DEALLOCATE PREPARE stmt;
	END IF;
END;