-- UP
ALTER TABLE `_migrations` 
	CHANGE `up_script` `up_script` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `up_result` `up_result` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `down_script` `down_script` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `down_result` `down_result` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

-- DOWN
ALTER TABLE `_migrations` 
	CHANGE `up_script` `up_script` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `up_result` `up_result` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `down_script` `down_script` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, 
	CHANGE `down_result` `down_result` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;