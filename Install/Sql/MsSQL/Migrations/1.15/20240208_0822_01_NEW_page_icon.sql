-- UP

ALTER TABLE [exf_page]
	ADD [icon] varchar(300) NULL;

-- DOWN

ALTER TABLE [exf_page]
	DROP COLUMN [icon];
