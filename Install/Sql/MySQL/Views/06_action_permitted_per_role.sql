/* View of permitted pages per role */
CREATE OR REPLACE VIEW exf_action_permitted_per_role AS
SELECT a.oid as app_oid, oa.oid as action_oid, r.oid as role_oid,
(CASE WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE AUTHORIZATION_POLICY.target_user_role_oid = r.oid AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'P') THEN 1
  WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY
  WHERE AUTHORIZATION_POLICY.target_user_role_oid = r.oid AND AUTHORIZATION_POLICY.target_object_action_oid = oa.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'D') THEN 0
 ELSE NULL END) AS Permitted
FROM exf_object_action oa
JOIN exf_user_role r on oa.action_app_oid = r.app_oid
JOIN exf_app a on oa.action_app_oid = a.oid;