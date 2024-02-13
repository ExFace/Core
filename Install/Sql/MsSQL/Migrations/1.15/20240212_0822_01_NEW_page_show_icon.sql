-- UP

ALTER TABLE [exf_page]
	ADD [show_icon] tinyint NULL;

-- DOWN

ALTER TABLE [exf_page]
	DROP COLUMN [show_icon];
