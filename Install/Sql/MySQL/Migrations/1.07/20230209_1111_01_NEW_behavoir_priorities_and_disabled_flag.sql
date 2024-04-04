-- UP

ALTER TABLE `exf_object_behaviors`
	ADD COLUMN `priority` INT NULL DEFAULT NULL AFTER `description`,
	ADD COLUMN `disabled_flag` TINYINT NOT NULL DEFAULT '0' AFTER `priority`;
	
-- DOWN

ALTER TABLE `exf_object_behaviors`
	DROP COLUMN `priority`,
	DROP COLUMN `disabled_flag`;

