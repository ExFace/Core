-- UP

ALTER TABLE dbo.exf_page
	ALTER COLUMN icon NVARCHAR(max) NULL;

-- DOWN

ALTER TABLE dbo.exf_page
	ALTER COLUMN icon NVARCHAR(300) NULL;
