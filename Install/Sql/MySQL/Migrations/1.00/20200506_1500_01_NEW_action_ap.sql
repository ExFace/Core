-- UP

INSERT IGNORE INTO `exf_auth_point` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `name`, `class`, `descr`, `app_oid`, `default_effect_in_app`, `default_effect_local`, `combining_algorithm_in_app`, `combining_algorithm_local`, `disabled_flag`, `policy_prototype_class`, `target_user_role_applicable`, `target_page_group_applicable`, `target_facade_applicable`, `target_object_applicable`, `target_action_applicable`, `docs_path`) VALUES
(0x11ea8eddc766689f8ba98c04ba002958, '2020-05-05 16:36:16', '2020-05-05 16:53:43', 0x11e9545e8e69e0d8b95b00505689aada, 0x11ea8944856eaddaaa6e025041000001, 'Access to actions', '\\exface\\Core\\CommonLogic\\Security\\Authorization\\ActionAuthorizationPoint', NULL, 0x31000000000000000000000000000000, 'P', '', 'permit-overrides', '', '0', '\\exface\\Core\\CommonLogic\\Security\\Authorization\\ActionAuthorizationPolicy', '1', '0', '0', '1', '1', 'exface/Core/Docs/Security/Authorization/Authorization_points/Action_AP.md')
	
-- DOWN

DELETE FROM `exf_auth_point` WHERE oid = 0x11ea8eddc766689f8ba98c04ba002958;