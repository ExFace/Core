-- UP

ALTER TABLE `exf_attribute_compound`
	ADD COLUMN `value_prefix` VARCHAR(10) '' AFTER `sequence_index`,
	ADD COLUMN `value_suffix` VARCHAR(10) '' AFTER `value_prefix`,
	DROP COLUMN `delimiter`;

-- DOWN

ALTER TABLE `exf_attribute_compound`
	ADD COLUMN `delimiter` VARCHAR(3) NULL DEFAULT NULL AFTER `sequence_index`,
	DROP COLUMN `value_prefix`,
	DROP COLUMN `value_suffix`;
