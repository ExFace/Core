-- UP

ALTER TABLE `exf_user_role`
	ADD COLUMN `start_page_oid` BINARY(16) NULL DEFAULT NULL AFTER `sync_with_external_role_oid`;
	
-- DOWN

ALTER TABLE `exf_user_role`
	DROP COLUMN `start_page_oid`;