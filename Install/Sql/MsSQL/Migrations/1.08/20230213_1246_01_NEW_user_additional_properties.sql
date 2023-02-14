-- UP

ALTER TABLE dbo.exf_user_authenticator
	ADD properties_uxon nvarchar(max) NULL;
	
-- DOWN

ALTER TABLE dbo.exf_user_authenticator
	DROP COLUMN properties_uxon;


