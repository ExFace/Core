ALTER TABLE `exf_object` ADD `readable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `read_only_flag`;
ALTER TABLE `exf_object` ADD `writable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `readable_flag`;
UPDATE exf_object SET writable_flag = 0 WHERE read_only_flag = 1;
ALTER TABLE `exf_object` DROP `read_only_flag `;

ALTER TABLE `exf_data_source` ADD `readable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `read_only_flag`;
ALTER TABLE `exf_data_source` ADD `writable_flag` TINYINT(1) NOT NULL DEFAULT '1' AFTER `readable_flag`;
UPDATE exf_data_source SET writable_flag = 0 WHERE read_only_flag = 1;
ALTER TABLE `exf_data_source` DROP `read_only_flag`;