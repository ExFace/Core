-- UP

IF COL_LENGTH('dbo.exf_notification','read_on') IS NULL
	ALTER TABLE [dbo].[exf_notification]
	ADD [read_on] [datetime2](0) NULL;
	
-- DOWN

IF COL_LENGTH('dbo.exf_notification','read_on') IS NOT NULL
ALTER TABLE [dbo].[exf_notification]
	DROP COLUMN [read_on];