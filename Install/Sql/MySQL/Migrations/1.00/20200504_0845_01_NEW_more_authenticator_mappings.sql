-- UP

ALTER TABLE `exf_user_authenticator`
	ADD UNIQUE INDEX `Authenticator unique per user` (`user_oid`, `authenticator_id`) USING BTREE;
	
ALTER TABLE `exf_user_role_external`
	ADD COLUMN `authenticator_id` VARCHAR(100) NOT NULL AFTER `user_role_oid`,
	DROP COLUMN `authenticator_class`,
	DROP COLUMN `authenticator_name`,
	ADD UNIQUE INDEX `Alias unique per authenticator` (`authenticator_id`, `alias`) USING BTREE;
	
-- DOWN

ALTER TABLE `exf_user_authenticator`
	DROP INDEX `Authenticator unique per user`;
	
ALTER TABLE `exf_user_role_external`
	DROP COLUMN `authenticator_id`,
	DROP COLUMN `authenticator_class` VARCHAR(200) NOT NULL AFTER `user_role_oid`,
	DROP COLUMN `authenticator_name` VARCHAR(200) NOT NULL AFTER `authenticator_class`
	DROP INDEX `Alias unique per authenticator`;
	