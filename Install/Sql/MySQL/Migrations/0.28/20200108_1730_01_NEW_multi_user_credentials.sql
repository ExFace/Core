-- UP

ALTER TABLE `exf_data_connection_credentials`
	ADD COLUMN `name` VARCHAR(50) NOT NULL AFTER `data_connection_oid`,
	ADD COLUMN `data_connector_config` TEXT NULL DEFAULT NULL AFTER `name`,
	ADD COLUMN `private` TINYINT(1) NOT NULL DEFAULT 1 AFTER `data_connector_config`;
	
ALTER TABLE `exf_user_credentials`
	ADD COLUMN `data_connection_credentials_oid` BINARY(16) NOT NULL AFTER `user_oid`;
	
UPDATE exf_data_connection_credentials dc 
	SET dc.data_connector_config = (
			SELECT 
				uc.data_connector_config 
			FROM exf_user_credentials uc 
			WHERE dc.user_credentials_oid = uc.oid
		),
		dc.name = (
			SELECT 
				uc.name 
			FROM exf_user_credentials uc 
			WHERE dc.user_credentials_oid = uc.oid
		);

UPDATE exf_user_credentials uc 
	SET uc.data_connection_credentials_oid = (
			SELECT 
				dc.oid 
			FROM exf_data_connection_credentials dc
			WHERE dc.user_credentials_oid = uc.oid 
		);

ALTER TABLE `exf_user_credentials`
	DROP COLUMN `data_connector_config`,
	DROP COLUMN `name`;

-- DOWN

ALTER TABLE `exf_data_connection_credentials`
	DROP COLUMN `name`,
	DROP COLUMN `data_connector_config`,
	DROP COLUMN `private`;
	
ALTER TABLE `exf_user_credentials`
	ADD COLUMN `data_connector_config` TEXT NULL DEFAULT NULL AFTER `user_oid`,
	ADD COLUMN `name` VARCHAR(128) NOT NULL AFTER `user_oid`,
	DROP COLUMN `data_connection_credentials_oid`;
