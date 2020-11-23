-- UP

ALTER TABLE `exf_uxon_preset`
	ADD COLUMN `thumbnail` VARCHAR(250) NULL DEFAULT NULL AFTER `uxon_schema`;
	
-- DOWN

ALTER TABLE `exf_uxon_preset`
	DROP COLUMN `thumbnail`;