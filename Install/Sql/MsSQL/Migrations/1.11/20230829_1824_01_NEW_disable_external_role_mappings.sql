-- UP
	
ALTER TABLE dbo.exf_user_role_external
	ADD active_flag TINYINT NOT NULL DEFAULT 1;
	
-- DOWN

ALTER TABLE exf_user_role_external
	DROP COLUMN active_flag;
