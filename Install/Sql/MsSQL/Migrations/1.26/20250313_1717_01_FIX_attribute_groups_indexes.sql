-- UP

ALTER TABLE dbo.exf_attribute_group
	DROP CONSTRAINT IF EXISTS UQ_Name_Per_App;

CREATE UNIQUE INDEX UQ_exf_attribute_group_alias_per_object   
   ON dbo.exf_attribute_group (object_oid, alias);

-- DOWN

ALTER TABLE dbo.exf_attribute_group
	DROP CONSTRAINT IF EXISTS UQ_exf_attribute_group_alias_per_object;