-- UP

ALTER TABLE `exf_user`
	ADD COLUMN `company` VARCHAR(200) NULL DEFAULT NULL AFTER `modified_by_user_oid`,
	ADD COLUMN `position` VARCHAR(200) NULL DEFAULT NULL AFTER `company`;

	
-- DOWN

ALTER TABLE `exf_user`
	DROP COLUMN `company`,
	DROP COLUMN `position`;
