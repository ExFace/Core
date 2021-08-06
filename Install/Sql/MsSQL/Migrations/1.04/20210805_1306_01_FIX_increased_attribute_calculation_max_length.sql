-- UP

DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE exf_attribute DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('exf_attribute')
    AND c.name IN (N'attribute_formatter')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END

ALTER TABLE [dbo].[exf_attribute]
	ALTER COLUMN [attribute_formatter] NVARCHAR(max) NULL;

UPDATE [dbo].[exf_data_type] SET config_uxon = '{"show_values":false,"values":{"D":"Data","C":"Compound","X":"Calculated"}}' WHERE oid = 0x11ea438c00f52350bb290205857feb80;

UPDATE [dbo].[exf_attribute] SET attribute_type = 'X' WHERE attribute_formatter IS NOT NULL AND attribute_formatter != '';
	
-- DOWN

LTER TABLE [exf_attribute]
	ALTER COLUMN [attribute_formatter] NVARCHAR(200) NULL;