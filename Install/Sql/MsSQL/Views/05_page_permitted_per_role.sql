IF OBJECT_ID('dbo.exf_page_permitted_per_role', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_page_permitted_per_role];
GO;

/* View of permitted pages per role */
CREATE VIEW [dbo].[exf_page_permitted_per_role] AS
SELECT a.oid as app_oid, p.oid as page_oid, r.oid as role_oid,
(CASE WHEN EXISTS (SELECT AUTHORIZATION_POLICY.oid FROM exf_auth_policy AUTHORIZATION_POLICY 
  LEFT JOIN exf_page_group TARGET_PAGE_GROUP ON AUTHORIZATION_POLICY.target_page_group_oid = TARGET_PAGE_GROUP.oid  
  WHERE  TARGET_PAGE_GROUP.oid IN (SELECT page_group_oid from exf_page_group_pages WHERE page_oid = p.oid )
  AND AUTHORIZATION_POLICY.target_user_role_oid = r.oid AND AUTHORIZATION_POLICY.disabled_flag = 0 AND AUTHORIZATION_POLICY.effect = 'P') THEN 1 ELSE 0 END) AS Permitted
FROM exf_page p
JOIN exf_app a on p.app_oid = a.oid
CROSS JOIN exf_user_role r