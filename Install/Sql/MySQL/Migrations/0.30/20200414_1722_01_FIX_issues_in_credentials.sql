-- UP

ALTER TABLE `exf_data_connection_credentials` CHANGE `name` `name` VARCHAR(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

-- DOWN

ALTER TABLE `exf_data_connection_credentials` CHANGE `name` `name` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;