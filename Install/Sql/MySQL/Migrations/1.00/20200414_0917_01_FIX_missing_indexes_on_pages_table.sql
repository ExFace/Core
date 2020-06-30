-- UP

ALTER TABLE `exf_page`
	ADD PRIMARY KEY (`oid`),
	ADD UNIQUE INDEX `Alias unique` (`alias`),
	ADD INDEX `Menu parent-index-visible` (`parent_oid`, `menu_index`, `menu_visible`);
	
-- DOWN

ALTER TABLE `exf_page`
	DROP INDEX `Alias unique`,
	DROP INDEX `Menu parent-index-visible`;