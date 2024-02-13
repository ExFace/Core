-- UP

IF COL_LENGTH('dbo.exf_page','icon') IS NULL
ALTER TABLE [dbo].[exf_page]
	ADD [icon] varchar(300) NULL;

IF COL_LENGTH('dbo.exf_page','icon_set') IS NULL
ALTER TABLE [dbo].[exf_page]
	ADD [icon_set] varchar(100) NULL;

-- DOWN

IF COL_LENGTH('dbo.exf_page','icon') IS NOT NULL
ALTER TABLE [dbo].[exf_page]
	DROP COLUMN [icon_set];
	
IF COL_LENGTH('dbo.exf_page','icon_set') IS NOT NULL
ALTER TABLE [dbo].[exf_page]
	DROP COLUMN [icon];
