-- UP

ALTER TABLE `exf_attribute`
	CHANGE COLUMN `attribute_formatter` `attribute_formatter` TEXT NULL COLLATE 'utf8_general_ci' AFTER `data_properties`;

UPDATE exf_data_type SET config_uxon = '{"show_values":false,"values":{"D":"Data","C":"Compound","X":"Calculated"}}' WHERE oid = 0x11ea438c00f52350bb290205857feb80;

UPDATE exf_attribute SET attribute_type = 'X' WHERE attribute_formatter IS NOT NULL AND attribute_formatter != '';
	
-- DOWN

ALTER TABLE `exf_attribute`
	CHANGE COLUMN `attribute_formatter` `attribute_formatter` VARCHAR(200) NULL COLLATE 'utf8_general_ci' AFTER `data_properties`;