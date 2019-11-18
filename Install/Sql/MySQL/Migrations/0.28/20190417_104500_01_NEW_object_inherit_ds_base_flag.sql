-- UP
ALTER TABLE `exf_object` ADD `inherit_data_source_base_object` TINYINT(1) NOT NULL DEFAULT '1' AFTER `data_source_oid`;

-- DOWN
ALTER TABLE `exf_object`
  DROP `inherit_data_source_base_object`;