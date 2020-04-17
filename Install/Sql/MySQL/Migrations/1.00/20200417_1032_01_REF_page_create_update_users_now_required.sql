-- UP

UPDATE `exf_page` p SET p.modified_by_user_oid = 0x00000000000000000000000000000000 WHERE p.modified_by_user_oid IS NULL;
UPDATE `exf_page` p SET p.created_by_user_oid = 0x00000000000000000000000000000000 WHERE p.created_by_user_oid IS NULL;

ALTER TABLE `exf_page`
	CHANGE COLUMN `created_by_user_oid` `created_by_user_oid` BINARY(16) NOT NULL AFTER `modified_on`,
	CHANGE COLUMN `modified_by_user_oid` `modified_by_user_oid` BINARY(16) NOT NULL AFTER `created_by_user_oid`;
	
-- DOWN

ALTER TABLE `exf_page`
	CHANGE COLUMN `created_by_user_oid` `created_by_user_oid` BINARY(16) NULL AFTER `modified_on`,
	CHANGE COLUMN `modified_by_user_oid` `modified_by_user_oid` BINARY(16) NULL AFTER `created_by_user_oid`;
	