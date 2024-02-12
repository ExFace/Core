-- UP

ALTER TABLE `exf_page`
	ADD COLUMN `show_icon` tinyint NULL;
	
-- DOWN

ALTER TABLE `exf_page`
	DROP COLUMN `show_icon`;