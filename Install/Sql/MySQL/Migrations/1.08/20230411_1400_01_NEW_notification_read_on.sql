-- UP

ALTER TABLE `exf_notification`
	ADD COLUMN read_on datetime NULL;
	
-- DOWN

ALTER TABLE `exf_notification`
	DROP COLUMN read_on;