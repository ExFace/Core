-- UP

ALTER TABLE `exf_user`
	ADD COLUMN `password` VARCHAR(60) NULL AFTER `username`;

-- DOWN

ALTER TABLE `exf_user`
	DROP COLUMN `password`;
