-- UP
	
ALTER TABLE dbo.exf_user_authenticator   
	ADD CONSTRAINT UC_user_authenticator_username UNIQUE (authenticator_username, authenticator_id);   

-- DOWN

ALTER TABLE dbo.exf_user_authenticator   
	DROP CONSTRAINT UC_user_authenticator_username;