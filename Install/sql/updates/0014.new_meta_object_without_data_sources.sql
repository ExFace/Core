ALTER TABLE `exf_object` 
	CHANGE `data_address` `data_address` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT 'Where the object is located in the data source (e.g. table in SQL)', 
	CHANGE `data_source_oid` `data_source_oid` BINARY(16) NULL;