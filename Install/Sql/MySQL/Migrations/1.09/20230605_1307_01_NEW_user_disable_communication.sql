-- UP
	
ALTER TABLE `exf_user`
	ADD COLUMN `disabled_communication_flag` TINYINT NOT NULL DEFAULT '0' AFTER `disabled_flag`;
	

	
-- DOWN

ALTER TABLE `exf_user`
	DROP COLUMN `disabled_communication_flag`;
