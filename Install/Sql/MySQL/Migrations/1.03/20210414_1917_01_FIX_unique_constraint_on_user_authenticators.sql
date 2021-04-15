-- UP

ALTER TABLE `exf_user_authenticator`
	ADD UNIQUE INDEX `Username unique per authenticator` (`authenticator_username`, `authenticator_id`) USING BTREE;
	
-- DOWN

ALTER TABLE `exf_user_authenticator`
	DROP INDEX `Username unique per authenticator`;