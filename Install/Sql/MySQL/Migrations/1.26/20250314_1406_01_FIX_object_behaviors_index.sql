-- UP

ALTER TABLE `exf_object_behaviors`
	ADD INDEX `IX_object` (`object_oid`);

-- DOWN

ALTER TABLE `exf_object_behaviors`
	DROP INDEX `IX_object`;