-- UP

ALTER TABLE dbo.exf_attribute
    ADD
        abbreviation VARCHAR(10) NULL,
        icon VARCHAR(MAX) NULL,  -- SQL Server 'TEXT' is deprecated; use VARCHAR(MAX)
        icon_set VARCHAR(100) NULL;

-- DOWN
-- Do not delete columns to avoid losing data!