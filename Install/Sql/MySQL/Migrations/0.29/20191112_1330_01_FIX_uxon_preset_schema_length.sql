-- UP

ALTER TABLE `exf_uxon_preset` CHANGE `uxon_schema` `uxon_schema` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

-- DOWN

ALTER TABLE `exf_uxon_preset` CHANGE `uxon_schema` `uxon_schema` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;