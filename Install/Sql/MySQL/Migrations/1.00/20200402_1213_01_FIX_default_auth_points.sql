-- UP

INSERT IGNORE INTO `exf_auth_point` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `name`, `alias`, `descr`, `app_oid`, `default_effect`, `combining_algorithm`, `active_flag`, `target_user_role_applicable`, `target_page_group_applicable`, `target_facade_applicable`, `target_object_applicable`, `target_action_applicable`) VALUES
(0x11ea5ede96e738f6b9920205857feb80, '2020-03-05 12:41:34', '2020-03-27 21:28:15', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Access to UI pages', 'PAGE_ACCESS', '', 0x31000000000000000000000000000000, 'P', 'permit-unless-deny', 1, 1, 1, 0, 0, 0),
(0x11ea6c42dfac007ba3480205857feb80, '2020-03-22 13:41:44', '2020-03-26 17:01:55', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Access to contexts', 'CONTEXT_ACCESS', NULL, 0x31000000000000000000000000000000, 'P', 'permit-unless-deny', 1, 1, 0, 0, 0, 0);

-- DOWN

