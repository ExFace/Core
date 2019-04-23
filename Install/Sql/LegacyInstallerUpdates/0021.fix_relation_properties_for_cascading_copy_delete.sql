ALTER TABLE `exf_attribute` 
	CHANGE `copy_with_related_object` `copy_with_related_object` TINYINT(1) NULL DEFAULT NULL, 
	CHANGE `delete_with_related_object` `delete_with_related_object` TINYINT(1) NULL DEFAULT NULL;