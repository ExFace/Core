-- UP

IF COL_LENGTH('dbo.exf_user','company') IS NULL
ALTER TABLE exf_user
	ADD [company] NVARCHAR(200) NULL;
	
IF COL_LENGTH('dbo.exf_user','position') IS NULL
ALTER TABLE exf_user
	ADD [position] NVARCHAR(200) NULL;

	
-- DOWN

IF COL_LENGTH('dbo.exf_user','company') IS NOT NULL
ALTER TABLE exf_user DROP COLUMN [company];

IF COL_LENGTH('dbo.exf_user','position') IS NOT NULL
ALTER TABLE exf_user DROP COLUMN [position];
