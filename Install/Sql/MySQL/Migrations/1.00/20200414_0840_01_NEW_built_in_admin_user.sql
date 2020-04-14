-- UP

INSERT IGNORE INTO `exf_user` (`oid`, `first_name`, `last_name`, `username`, `password`, `locale`, `email`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`) VALUES
(0x11e8fe1c902c8ebea23ee4b318306b9a, NULL, NULL, 'admin', '$2y$10$YJQqUCShXFsQIfgU1xJh.esOmkuiNIpWjuv42ZNtLuq.vFJDgG0rO', 'en_US', 'admin@exface.com', '2018-12-12 14:45:36', '2019-02-14 15:28:37', 0x00000000000000000000000000000000, 0x00000000000000000000000000000000);

INSERT IGNORE INTO `exf_user_role_users` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `user_role_oid`, `user_oid`) VALUES
(0x11ea690653bda9a4a3480205857feb80, '2020-03-18 10:50:45', '2020-03-18 10:50:45', 0x00000000000000000000000000000000, 0x00000000000000000000000000000000, 0x11ea6c428d7e7e9fa3480205857feb80, 0x11e8fe1c902c8ebea23ee4b318306b9a);