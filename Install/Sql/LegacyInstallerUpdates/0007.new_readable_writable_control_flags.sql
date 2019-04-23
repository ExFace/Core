ALTER TABLE `exf_object` ADD `read_only_flag` TINYINT(1) NOT NULL DEFAULT '0' AFTER `data_address_properties`;

ALTER TABLE `exf_attribute` ADD `attribute_readable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `object_uid_flag`;
ALTER TABLE `exf_attribute` ADD `attribute_writable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `attribute_readable_flag`;