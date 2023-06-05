-- UP
	
IF COL_LENGTH('dbo.exf_user','disabled_communication_flag') IS NULL
ALTER TABLE dbo.exf_user
	ADD [disabled_communication_flag] TINYINT NULL;

	
-- DOWN

IF COL_LENGTH('dbo.exf_user','disabled_communication_flag') IS NOT NULL
ALTER TABLE dbo.exf_user DROP COLUMN [disabled_communication_flag];
