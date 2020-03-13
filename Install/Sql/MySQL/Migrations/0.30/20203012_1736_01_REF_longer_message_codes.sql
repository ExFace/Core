-- UP

ALTER TABLE `exf_message` CHANGE `code` `code` VARCHAR(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
	
-- DOWN

ALTER TABLE `exf_message` CHANGE `code` `code` VARCHAR(8) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
