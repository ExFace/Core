CREATE PROCEDURE execute_sql_on_existing_column(
    IN name_of_table VARCHAR(255),
    IN name_of_column VARCHAR(255),
    IN sql_statement VARCHAR(21845)
)
BEGIN
    -- Check if the column exists
    IF EXISTS (
        SELECT *
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = name_of_table
          AND COLUMN_NAME = name_of_column
    )
    THEN
        -- drop column
        SET @sql = sql_statement;
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    ELSE
        -- Handle the situation where the column does not exist
        SELECT CONCAT('Cannot execute SQL on column ', name_of_column, ' because it does not exist in table ', name_of_table) AS Result;
    END IF;
END;