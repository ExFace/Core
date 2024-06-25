-- UP

ALTER TABLE `exf_page`
	CHANGE COLUMN `icon` `icon` TEXT NULL;

-- DOWN

ALTER TABLE `exf_page`
	CHANGE COLUMN `icon` `icon` VARCHAR(300) NULL;
