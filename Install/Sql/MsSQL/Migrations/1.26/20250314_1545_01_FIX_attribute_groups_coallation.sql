-- UP

IF EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'UQ_exf_attribute_group_alias_per_object'
)
DROP INDEX [UQ_exf_attribute_group_alias_per_object] ON [dbo].[exf_attribute_group];

ALTER TABLE [dbo].[exf_attribute_group] ALTER COLUMN [name] nvarchar(50) NOT NULL;
ALTER TABLE [dbo].[exf_attribute_group] ALTER COLUMN [alias] nvarchar(50) NOT NULL;
ALTER TABLE [dbo].[exf_attribute_group] ALTER COLUMN [description] nvarchar(200) NULL;

CREATE UNIQUE INDEX UQ_exf_attribute_group_alias_per_object   
   ON dbo.exf_attribute_group (object_oid, alias);

-- DOWN