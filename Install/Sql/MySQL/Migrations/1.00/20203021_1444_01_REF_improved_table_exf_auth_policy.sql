-- UP

ALTER TABLE `exf_page_group`
	ADD UNIQUE INDEX `Name unique per app` (`name`, `app_oid`) USING BTREE;
	
ALTER TABLE `exf_page_group_pages`
	ADD UNIQUE INDEX `Page unique per group` (`page_oid`, `page_group_oid`);
	
ALTER TABLE `exf_user_role_users`
	ADD UNIQUE INDEX `Role unique per user` (`user_oid`, `user_role_oid`);
		
-- DOWN

ALTER TABLE `exf_page_group`
	DROP INDEX `Name unique per app`;
	
ALTER TABLE `exf_page_group_pages`
	DROP INDEX `Page unique per group`;
	
ALTER TABLE `exf_user_role_users`
	DROP INDEX `Role unique per user`;
