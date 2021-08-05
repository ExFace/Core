-- UP

ALTER TABLE [dbo].[exf_attribute]
	ALTER COLUMN [attribute_formatter] NVARCHAR(max) NULL;

UPDATE [dbo].[exf_data_type] SET config_uxon = '{"show_values":false,"values":{"D":"Data","C":"Compound","X":"Calculated"}}' WHERE oid = 0x11ea438c00f52350bb290205857feb80;

UPDATE [dbo].[exf_attribute] SET attribute_type = 'X' WHERE attribute_formatter IS NOT NULL AND attribute_formatter != '';
	
-- DOWN

LTER TABLE [exf_attribute]
	ALTER COLUMN [attribute_formatter] NVARCHAR(200) NULL;