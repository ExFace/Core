/* View of permitted pages per user */
CREATE OR REPLACE VIEW exf_page_permitted_per_user AS
SELECT p.oid as page_oid, u.oid as user_oid,
(CASE WHEN EXISTS
 (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY 
  LEFT JOIN exf_page_group TARGET_PAGE_GROUP ON AUTHORIZATION_POLICY.target_page_group_oid = TARGET_PAGE_GROUP.oid
  JOIN exf_auth_point ap ON ap.oid = AUTHORIZATION_POLICY.auth_point_oid AND ap.class LIKE '%UiPageAuthorizationPoint%'
  WHERE
  (
      (TARGET_PAGE_GROUP.oid IS NULL AND AUTHORIZATION_POLICY.target_user_role_oid IN (SELECT uru.user_role_oid from exf_user_role_users uru WHERE uru.user_oid = u.oid)) OR
      (TARGET_PAGE_GROUP.oid IN (SELECT page_group_oid from exf_page_group_pages WHERE page_oid = p.oid))
  )
  AND AUTHORIZATION_POLICY.target_user_role_oid IN (SELECT uru.user_role_oid from exf_user_role_users uru WHERE uru.user_oid = u.oid)
  AND AUTHORIZATION_POLICY.disabled_flag = 0
  AND AUTHORIZATION_POLICY.effect = 'P')
 THEN 1
 ELSE 0
 END) AS Permitted
FROM exf_page p
CROSS JOIN exf_user u;