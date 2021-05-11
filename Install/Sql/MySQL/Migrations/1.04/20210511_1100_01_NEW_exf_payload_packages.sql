-- UP

CREATE TABLE `exf_payload_packages` (
	`oid` BINARY(16) NOT NULL,
	`app_alias` VARCHAR(128) NOT NULL COLLATE 'utf8_general_ci',
	`type` VARCHAR(50) NOT NULL COLLATE 'utf8_general_ci',
	`url` VARCHAR(250) NOT NULL COLLATE 'utf8_general_ci',
	`version` VARCHAR(50) NULL COLLATE 'utf8_general_ci',
	`created_on` DATETIME NOT NULL,
	`modified_on` DATETIME NOT NULL,
	`created_by_user_oid` BINARY(16) NULL DEFAULT NULL,
	`modified_by_user_oid` BINARY(16) NULL DEFAULT NULL,
	PRIMARY KEY (`oid`) USING BTREE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
	
-- DOWN

DROP TABLE `exf_payload_packages`;