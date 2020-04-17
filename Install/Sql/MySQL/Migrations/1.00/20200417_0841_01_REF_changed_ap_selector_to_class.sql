-- UP

ALTER TABLE `exf_auth_point`
	ADD COLUMN `class` VARCHAR(200) NOT NULL AFTER `name`,
	DROP COLUMN `alias`;

UPDATE `exf_auth_point` SET `class`='\\exface\\Core\\CommonLogic\\Security\\Authorization\\UiPageAuthorizationPoint' WHERE  `oid`=0x11EA5EDE96E738F6B9920205857FEB80;
UPDATE `exf_auth_point` SET `class`='\\exface\\Core\\CommonLogic\\Security\\Authorization\\ContextAuthorizationPoint' WHERE  `oid`=0x11EA6C42DFAC007BA3480205857FEB80;

ALTER TABLE `exf_auth_point`
	ADD UNIQUE INDEX `Class unique` (`class`);
	
-- DOWN

ALTER TABLE `exf_auth_point`
	ADD COLUMN `alias` VARCHAR(50) NOT NULL AFTER `name`,
	DROP COLUMN `class`;
	
UPDATE `exf_auth_point` SET `alias`='PAGE_ACCESS' WHERE  `oid`=0x11EA5EDE96E738F6B9920205857FEB80;
UPDATE `exf_auth_point` SET `alias`='CONTEXT_ACCESS' WHERE  `oid`=0x11EA6C42DFAC007BA3480205857FEB80;

ALTER TABLE `exf_auth_point`
	DROP INDEX `Class unique`;
	