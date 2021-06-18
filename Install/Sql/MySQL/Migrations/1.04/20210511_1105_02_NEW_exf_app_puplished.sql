-- UP

ALTER TABLE `exf_app`
	ADD COLUMN `puplished` TINYINT(1) NOT NULL DEFAULT '0' AFTER `modified_by_user_oid`;
	
-- DOWN

ALTER TABLE `exf_app`
	DROP COLUMN `puplished`;