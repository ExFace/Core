-- UP

/*
This migration fixes initial installs, that fail because current code actually
assumes, that the data authorization point is always there. There are multiple
places, that disable it (e.g. in the DataInstaller), but that does not work
if the authorization point simply is not there.
*/

INSERT IGNORE INTO `exf_auth_point` (
    `oid`,
    `created_on`,
    `modified_on`,
    `created_by_user_oid`,
    `modified_by_user_oid`,
    `name`,
    `class`,
    `descr`,
    `app_oid`,
    `default_effect_in_app`,
    `default_effect_local`,
    `combining_algorithm_in_app`,
    `combining_algorithm_local`,
    `disabled_flag`,
    `policy_prototype_class`,
    `target_user_role_applicable`,
    `target_page_group_applicable`,
    `target_facade_applicable`,
    `target_object_applicable`,
    `target_action_applicable`,
    `target_app_applicable`,
    `docs_path`
) VALUES (
     0x11ec8990fa549caa8990025041000001,
     '2022-04-24 17:05:28',
     '2023-12-20 12:16:07',
     0x31000000000000000000000000000000,
     0x31000000000000000000000000000000,
     'Access to data',
     '\\exface\\Core\\CommonLogic\\Security\\Authorization\\DataAuthorizationPoint',
     NULL,
     0x31000000000000000000000000000000,
     'P',
     NULL,
     'permit-overrides',
     NULL,
     0,
     '\\exface\\Core\\CommonLogic\\Security\\Authorization\\DataAuthorizationPolicy',
     1,
     0,
     0,
     1,
     0,
     1,
     'exface/Core/Docs/Security/Authorization/Authorization_points/Data_AP.md'
 );

-- DOWN