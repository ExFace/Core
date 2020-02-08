-- UP

ALTER TABLE `exf_attribute_compound`
	ADD UNIQUE INDEX `Sequence index unique per compound attribute` (`compound_attribute_oid`, `sequence_index`),
	ADD UNIQUE INDEX `Use each component attribute only once per compound` (`compound_attribute_oid`, `attribute_oid`);
	
-- DOWN

ALTER TABLE `exf_attribute_compound`
	DROP INDEX `Use each component attribute only once per compound`,
	DROP INDEX `Sequence index unique per compound attribute`;
