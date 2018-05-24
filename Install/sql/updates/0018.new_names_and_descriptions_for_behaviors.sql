ALTER TABLE `exf_object_behaviors` ADD `name` VARCHAR(100) NOT NULL AFTER `object_oid`;
ALTER TABLE `exf_object_behaviors` ADD `description` TEXT NULL AFTER `config_uxon`;
