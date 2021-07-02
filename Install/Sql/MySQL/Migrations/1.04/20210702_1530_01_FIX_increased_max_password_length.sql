-- UP

ALTER TABLE `exf_user`
	CHANGE COLUMN `password` `password` VARCHAR(300) NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `username`;
	
-- DOWN

ALTER TABLE `exf_user`
	CHANGE COLUMN `password` `password` VARCHAR(60) NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `username`;