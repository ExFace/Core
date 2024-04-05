CREATE PROCEDURE execute_sql_on_missing_column(
    IN name_of_table VARCHAR(255),
    IN name_of_column VARCHAR(255),
    IN sql_statement VARCHAR(21845)
)
BEGIN
    -- Check if the column exists
    IF NOT EXISTS (
        SELECT *
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = name_of_table
          AND COLUMN_NAME = name_of_column
    )
    THEN
        -- insert column
        SET @sql = sql_statement;
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    ELSE
        -- Handle the situation where the column does already exists
        SELECT CONCAT('Column ', name_of_column, ' already exists in table ', name_of_table) AS Result;
    END IF;
END;