-- UP

ALTER TABLE `exf_user_role`
	CHANGE COLUMN `alias` `alias` VARCHAR(50) NOT NULL COLLATE 'utf8_general_ci' AFTER `name`;
	
-- DOWN

ALTER TABLE `exf_user_role`
	CHANGE COLUMN `alias` `alias` VARCHAR(50) NULL COLLATE 'utf8_general_ci' AFTER `name`;

