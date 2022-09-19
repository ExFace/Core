IF OBJECT_ID('dbo.exf_user_policy', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_user_policy];
GO
CREATE VIEW [dbo].[exf_user_policy] (
   [user_oid], 
   [authorization_policy_oid]
) AS 
   SELECT 
      u.oid AS user_oid, 
      apol.oid AS authorization_policy_oid
   FROM (((dbo.exf_user  AS u 
      LEFT JOIN dbo.exf_user_role_users  AS uru ON uru.user_oid = u.oid) 
      LEFT JOIN dbo.exf_auth_policy  AS apol ON apol.target_user_role_oid = uru.user_role_oid) 
      INNER JOIN dbo.exf_auth_point  AS apt ON apol.auth_point_oid = apt.oid)
   WHERE (apt.target_user_role_applicable = 1)
    UNION ALL
   SELECT 
      u.oid AS user_oid, 
      apol.oid AS authorization_policy_oid
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