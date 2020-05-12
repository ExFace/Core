-- UP

ALTER TABLE `exf_user`
	ADD COLUMN `disabled_flag` TINYINT(1) NULL DEFAULT '0' AFTER `email`
	
-- DOWN

ALTER TABLE `exf_user`
	DROP COLUMN `disabled_flag`