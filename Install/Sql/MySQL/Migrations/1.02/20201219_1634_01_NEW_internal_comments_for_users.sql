-- UP

ALTER TABLE `exf_user`
	ADD COLUMN `comments` TEXT NULL AFTER `disabled_flag`;
	
-- DOWN

ALTER TABLE `exf_user`
	DROP COLUMN `comments`;