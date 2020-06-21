IF OBJECT_ID('dbo.exf_user_policy', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_user_policy];

CREATE VIEW [dbo].[exf_user_policy] (
   [user_oid], 
   [oid], 
   [created_on], 
   [modified_on], 
   [created_by_user_oid], 
   [modified_by_user_oid], 
   [name], 
   [descr], 
   [effect], 
   [disabled_flag], 
   [app_oid], 
   [auth_point_oid], 
   [target_page_group_oid], 
   [target_user_role_oid], 
   [target_object_oid], 
   [target_object_action_oid], 
   [target_action_class_path], 
   [target_facade_class_path], 
   [condition_uxon])
AS 
   SELECT 
      u.oid AS user_oid, 
      apol.oid AS oid, 
      apol.created_on AS created_on, 
      apol.modified_on AS modified_on, 
      apol.created_by_user_oid AS created_by_user_oid, 
      apol.modified_by_user_oid AS modified_by_user_oid, 
      apol.name AS name, 
      apol.descr AS descr, 
      apol.effect AS effect, 
      apol.disabled_flag AS disabled_flag, 
      apol.app_oid AS app_oid, 
      apol.auth_point_oid AS auth_point_oid, 
      apol.target_page_group_oid AS target_page_group_oid, 
      apol.target_user_role_oid AS target_user_role_oid, 
      apol.target_object_oid AS target_object_oid, 
      apol.target_object_action_oid AS target_object_action_oid, 
      apol.target_action_class_path AS target_action_class_path, 
      apol.target_facade_class_path AS target_facade_class_path, 
      apol.condition_uxon AS condition_uxon
   FROM (((dbo.exf_user  AS u 
      LEFT JOIN dbo.exf_user_role_users  AS uru 
      ON ((uru.user_oid = u.oid))) 
      LEFT JOIN dbo.exf_auth_policy  AS apol 
      ON ((apol.target_user_role_oid = uru.user_role_oid))) 
      INNER JOIN dbo.exf_auth_point  AS apt 
      ON ((apol.auth_point_oid = apt.oid)))
   WHERE (apt.target_user_role_applicable = 1)
    UNION ALL
   SELECT 
      u.oid AS user_oid, 
      apol.oid AS oid, 
      apol.created_on AS created_on, 
      apol.modified_on AS modified_on, 
      apol.created_by_user_oid AS created_by_user_oid, 
      apol.modified_by_user_oid AS modified_by_user_oid, 
      apol.name AS name, 
      apol.descr AS descr, 
      apol.effect AS effect, 
      apol.disabled_flag AS disabled_flag, 
      apol.app_oid AS app_oid, 
      apol.auth_point_oid AS auth_point_oid, 
      apol.target_page_group_oid AS target_page_group_oid, 
      apol.target_user_role_oid AS target_user_role_oid, 
      apol.target_object_oid AS target_object_oid, 
      apol.target_object_action_oid AS target_object_action_oid, 
      apol.target_action_class_path AS target_action_class_path, 
      apol.target_facade_class_path AS target_facade_class_path, 
      apol.condition_uxon AS condition_uxon
   FROM ((dbo.exf_user  AS u 
      LEFT JOIN dbo.exf_auth_policy  AS apol 
      ON ((
         CASE 
            WHEN CAST(apol.target_user_role_oid AS varchar(max)) IS NULL THEN 1
            ELSE 0
         END <> 0 OR ((apol.target_user_role_oid = 0x11ea6fa3cab9a380a3480205857feb80) AND (u.oid <> 0x00000000000000000000000000000000))))) 
      INNER JOIN dbo.exf_auth_point  AS apt 
      ON ((apol.auth_point_oid = apt.oid)))
   WHERE (apt.target_user_role_applicable = 1);