-- UP

ALTER TABLE `exf_pwa`
	ADD `version_no` varchar(10) NULL,
	ADD `version_build` varchar(20) NULL;
	
ALTER TABLE `exf_pwa_build`
ADD `version` varchar(50) NULL;
	
-- DOWN

ALTER TABLE `exf_pwa`
	DROP `version_no`,
	DROP `version_build`;
	
ALTER TABLE `exf_pwa_build`
DROP `version`;