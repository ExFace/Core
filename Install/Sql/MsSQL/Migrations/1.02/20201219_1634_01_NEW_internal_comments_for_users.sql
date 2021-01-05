-- UP

ALTER TABLE [dbo].[exf_user]
	ADD COLUMN [comments] [nvarchar](max) DEFAULT NULL;
	
-- DOWN

ALTER TABLE [dbo].[exf_user]
	DROP COLUMN [comments];