ALTER TABLE `exf_data_type` ADD `app_oid` BINARY(16) NOT NULL AFTER `data_type_alias`;
update exf_data_type set app_oid = 0x31000000000000000000000000000000;

ALTER TABLE `exf_data_type` ADD `name` VARCHAR(64) NOT NULL AFTER `app_oid`;
update exf_data_type set name = data_type_alias;

ALTER TABLE `exf_data_type` ADD `prototype` VARCHAR(128) NOT NULL AFTER `name`;
update exf_data_type set prototype = CONCAT('exface/Core/DataTypes/', data_type_alias, 'DataType.php');