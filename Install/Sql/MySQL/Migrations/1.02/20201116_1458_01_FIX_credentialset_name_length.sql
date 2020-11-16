-- UP

ALTER TABLE `exf_data_connection_credentials`
	CHANGE COLUMN `name` `name` VARCHAR(200) NOT NULL COLLATE 'utf8_general_ci' AFTER `data_connection_oid`;

-- DOWN

ALTER TABLE `exf_data_connection_credentials`
	CHANGE COLUMN `name` `name` VARCHAR(50) NOT NULL COLLATE 'utf8_general_ci' AFTER `data_connection_oid`;