-- UP

ALTER TABLE `exf_attribute_group`
	DROP INDEX `Name unique per app`;
  
ALTER TABLE `exf_attribute_group`
	ADD UNIQUE INDEX `UQ_alias_per_object` (`object_oid`, `alias`);

-- DOWN

ALTER TABLE `exf_attribute_group`
	DROP INDEX `UQ_alias_per_object`;