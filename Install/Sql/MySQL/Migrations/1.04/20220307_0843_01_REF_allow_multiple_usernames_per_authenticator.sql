-- UP

ALTER TABLE `exf_user_authenticator`
	DROP INDEX `Authenticator unique per user`;

	
-- DOWN

ALTER TABLE `exf_user_authenticator`
	ADD UNIQUE INDEX `Authenticator unique per user` (`user_oid`, `authenticator_id`) USING BTREE;