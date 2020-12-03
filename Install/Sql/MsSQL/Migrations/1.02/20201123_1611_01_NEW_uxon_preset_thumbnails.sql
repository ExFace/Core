-- UP

ALTER TABLE [dbo].[exf_uxon_preset]
	ADD COLUMN [thumbnail] [nvarchar](250) DEFAULT NULL;
	
-- DOWN

ALTER TABLE [dbo].[exf_uxon_preset]
	DROP COLUMN [thumbnail];