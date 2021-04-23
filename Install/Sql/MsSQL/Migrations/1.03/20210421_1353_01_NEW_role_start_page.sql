-- UP

ALTER TABLE [dbo].[exf_user_role]
	ADD [start_page_oid] binary(16) DEFAULT NULL;
	
-- DOWN

ALTER TABLE [dbo].[exf_user_role]
	DROP COLUMN [start_page_oid];