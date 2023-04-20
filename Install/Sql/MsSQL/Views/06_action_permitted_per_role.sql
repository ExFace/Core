IF OBJECT_ID('dbo.exf_action_permitted_per_role', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_action_permitted_per_role];
GO;

/* View of permitted pages per role */
CREATE OR REPLACE VIEW exf_action_permitted_per_role AS
SELECT a.oid as app_oid, oa.oid as action_oid, r.oid as role_oid,
(CASE WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE (AUTHORIZATION_POLICY.target_user_role_oid = r.oid OR AUTHORIZATION_POLICY.target_user_role_oid IS NULL) AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'P') THEN 1
  WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE (AUTHORIZATION_POLICY.target_user_role_oid = r.oid OR AUTHORIZATION_POLICY.target_user_role_oid IS NULL) AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'D') THEN 0
 ELSE 1 END) AS Permitted
FROM exf_object_action oa
JOIN exf_app a on oa.action_app_oid = a.oid
CROSS JOIN exf_user_role r;