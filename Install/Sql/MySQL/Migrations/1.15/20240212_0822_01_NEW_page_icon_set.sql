-- UP

ALTER TABLE `exf_page`
	ADD COLUMN `icon_set` varchar(100) NULL;
	
-- DOWN

ALTER TABLE `exf_page`
	DROP COLUMN `icon_set`;