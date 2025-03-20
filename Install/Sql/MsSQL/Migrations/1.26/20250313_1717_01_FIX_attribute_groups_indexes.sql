-- UP

IF EXISTS (
    SELECT 1 
    FROM sys.objects 
    WHERE name = 'UQ_Name_Per_App' 
    AND type = 'UQ'
)
ALTER TABLE dbo.exf_attribute_group
	DROP CONSTRAINT UQ_Name_Per_App;

CREATE UNIQUE INDEX UQ_exf_attribute_group_alias_per_object   
   ON dbo.exf_attribute_group (object_oid, alias);

-- DOWN

DROP INDEX [UQ_exf_attribute_group_alias_per_object] ON [dbo].[exf_attribute_group];