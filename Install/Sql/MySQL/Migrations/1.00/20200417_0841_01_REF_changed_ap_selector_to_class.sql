-- UP

ALTER TABLE `exf_auth_point`
	ADD COLUMN `class` VARCHAR(200) NOT NULL AFTER `name`,
	DROP COLUMN `alias`;

UPDATE `alexa5`.`exf_auth_point` SET `class`='\\exface\\Core\\CommonLogic\\Security\\Authorization\\UiPageAuthorizationPoint' WHERE  `oid`=0x11EA5EDE96E738F6B9920205857FEB80;
UPDATE `alexa5`.`exf_auth_point` SET `class`='\\exface\\Core\\CommonLogic\\Security\\Authorization\\ContextAuthorizationPoint' WHERE  `oid`=0x11EA6C42DFAC007BA3480205857FEB80;

-- DOWN

ALTER TABLE `exf_auth_point`
	ADD COLUMN `alias` VARCHAR(50) NOT NULL AFTER `name`,
	DROP COLUMN `class`;
	
UPDATE `alexa5`.`exf_auth_point` SET `alias`='PAGE_ACCESS' WHERE  `oid`=0x11EA5EDE96E738F6B9920205857FEB80;
UPDATE `alexa5`.`exf_auth_point` SET `alias`='CONTEXT_ACCESS' WHERE  `oid`=0x11EA6C42DFAC007BA3480205857FEB80;
	