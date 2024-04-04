-- UP

ALTER TABLE `exf_user_authenticator`
	ADD COLUMN `properties_uxon` TEXT NULL DEFAULT NULL AFTER `last_authenticated_on`;
	
-- DOWN

ALTER TABLE `exf_user_authenticator`
	DROP COLUMN `properties_uxon`;


