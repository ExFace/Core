-- UP

INSERT IGNORE INTO `exf_auth_point` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `name`, `class`, `descr`, `app_oid`, `default_effect_in_app`, `default_effect_local`, `combining_algorithm_in_app`, `combining_algorithm_local`, `disabled_flag`, `policy_prototype_class`, `target_user_role_applicable`, `target_page_group_applicable`, `target_facade_applicable`, `target_object_applicable`, `target_action_applicable`) VALUES
(0x11ea8e12097517b8801f025041000001, '2020-05-04 16:17:42', '2020-05-04 16:17:42', 0x11ea8944856eaddaaa6e025041000001, 0x11ea8944856eaddaaa6e025041000001, 'Access to facades', '\\exface\\Core\\CommonLogic\\Security\\Authorization\\FacadeAuthorizationPoint', NULL, 0x31000000000000000000000000000000, 'P', NULL, 'permit-unless-deny', NULL, 0, '\\exface\\Core\\CommonLogic\\Security\\Authorization\\FacadeAuthorizationPolicy', 1, 0, 1, 0, 0);

	
-- DOWN

DELETE FROM `exf_auth_point` WHERE oid = 0x11ea8e12097517b8801f025041000001;
	