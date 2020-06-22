-- UP

INSERT INTO `exf_user` (`oid`, `first_name`, `last_name`, `username`, `password`, `locale`, `email`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`) VALUES
(0x00000000000000000000000000000000, '', '', 'guest', '$2y$10$uMF0FrpxJ3tmGUXsBnH47uFrLiYD/JLdvV7NwB6Kmlk7z11NCt6I6', '', '', '2020-03-25 13:28:11', '2020-03-25 13:28:11', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000);
		
INSERT INTO `exf_user_role_users` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `user_role_oid`, `user_oid`) VALUES
(0x11ea6e9d89990d87a3480205857feb80, '2020-03-25 13:35:48', '2020-03-25 13:35:48', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 0x11ea6c44b4d365f6a3480205857feb80, 0x00000000000000000000000000000000);

-- DOWN

DELETE from `exf_user` WHERE `oid` = 0x00000000000000000000000000000000;

DELETE from `exf_user_role_users` WHERE `oid` = 0x11ea6e9d89990d87a3480205857feb80;