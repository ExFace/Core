IF OBJECT_ID('dbo.exf_action_permitted_per_user', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_action_permitted_per_user];
GO;

CREATE VIEW [dbo].[exf_action_permitted_per_user] AS
SELECT oa.oid as action_oid, u.oid as user_oid,
(CASE WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE (AUTHORIZATION_POLICY.target_user_role_oid IS NULL OR AUTHORIZATION_POLICY.target_user_role_oid IN (SELECT uru.user_role_oid from exf_user_role_users uru WHERE uru.user_oid = u.oid)) AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'P') THEN 1
  WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE (AUTHORIZATION_POLICY.target_user_role_oid IS NULL OR AUTHORIZATION_POLICY.target_user_role_oid IN (SELECT uru.user_role_oid from exf_user_role_users uru WHERE uru.user_oid = u.oid)) AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'D') THEN 0
 ELSE 1 END) AS Permitted
FROM exf_object_action oa
CROSS JOIN exf_user u;