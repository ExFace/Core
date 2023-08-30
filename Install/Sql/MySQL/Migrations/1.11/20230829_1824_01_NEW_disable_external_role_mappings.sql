-- UP
	
ALTER TABLE `exf_user_role_external`
	ADD COLUMN `active_flag` TINYINT NOT NULL DEFAULT '1' AFTER `authenticator_id`;
	
-- DOWN

ALTER TABLE `exf_user_role_external`
	DROP COLUMN `active_flag`;
