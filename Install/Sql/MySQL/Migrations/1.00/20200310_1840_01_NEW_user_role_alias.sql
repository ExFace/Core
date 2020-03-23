-- UP

ALTER TABLE `exf_user_role`
	ADD COLUMN `alias` VARCHAR(50) NOT NULL AFTER `name`,
	ADD UNIQUE INDEX `Unique App+Alias` (`app_oid`, `alias`);
	
-- DOWN

ALTER TABLE `exf_user_role`
	DROP COLUMN `alias`,
	DROP INDEX `Unique App+Alias`;
