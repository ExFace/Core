-- UP

ALTER TABLE `exf_attribute`
	CHANGE COLUMN `attribute_formatter` `attribute_formatter` TEXT NULL COLLATE 'utf8_general_ci' AFTER `data_properties`;

UPDATE exf_attribute SET attribute_type = 'X' WHERE attribute_formatter IS NOT NULL AND attribute_formatter != '';
	
-- DOWN

LTER TABLE `exf_attribute`
	CHANGE COLUMN `attribute_formatter` `attribute_formatter` VARCHAR(200) NULL COLLATE 'utf8_general_ci' AFTER `data_properties`;