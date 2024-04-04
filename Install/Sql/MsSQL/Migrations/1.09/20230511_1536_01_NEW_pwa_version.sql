-- UP

ALTER TABLE [exf_pwa]
	ADD [version_no] varchar(10) NULL,
	[version_build] varchar(20) NULL;

ALTER TABLE [exf_pwa_build]
	ADD [version] varchar(50) NULL;
	
-- DOWN

ALTER TABLE [exf_pwa]
	DROP COLUMN [version_no],
	COLUMN [version_build];

ALTER TABLE [exf_pwa_build]
	DROP COLUMN [version];