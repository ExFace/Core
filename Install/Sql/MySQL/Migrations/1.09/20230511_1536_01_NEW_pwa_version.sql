-- UP

ALTER TABLE `exf_pwa`
	ADD COLUMN `version_no` varchar(10) NULL,
	ADD COLUMN `version_build` varchar(20) NULL;
	
ALTER TABLE `exf_pwa_build`
	ADD COLUMN `version` varchar(50) NULL;
	
-- DOWN

ALTER TABLE `exf_pwa`
	DROP COLUMN `version_no`,
	DROP COLUMN `version_build`;
	
ALTER TABLE `exf_pwa_build`
	DROP COLUMN `version`;