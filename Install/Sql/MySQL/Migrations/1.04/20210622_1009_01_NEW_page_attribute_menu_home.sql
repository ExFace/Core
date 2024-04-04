-- UP

ALTER TABLE `exf_page`
	ADD COLUMN `menu_home` TINYINT(1) NOT NULL DEFAULT '0' AFTER `menu_visible`;	
	
-- DOWN

ALTER TABLE `exf_page`
	DROP COLUMN `menu_home`;	