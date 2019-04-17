ALTER TABLE `exf_attribute` 
ADD `copy_with_related_object` TINYINT(1) NOT NULL DEFAULT 0 AFTER `related_object_special_key_attribute_oid`, 
ADD `delete_with_related_object` TINYINT(1) NOT NULL DEFAULT 0 AFTER `copy_with_related_object`;

update exf_attribute set delete_with_related_object = 1 where related_object_oid IS NOT NULL AND attribute_required_flag = 1 AND attribute_writable_flag = 1;