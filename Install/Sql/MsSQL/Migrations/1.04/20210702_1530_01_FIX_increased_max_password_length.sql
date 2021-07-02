-- UP

ALTER TABLE [dbo].[exf_user]
	ALTER COLUMN [password] NVARCHAR(300) NULL;
	
-- DOWN

ALTER TABLE [dbo].[exf_user]
	ALTER COLUMN [password] NVARCHAR(60) NULL;