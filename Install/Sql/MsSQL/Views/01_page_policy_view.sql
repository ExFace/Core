IF OBJECT_ID('dbo.exf_page_policy', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_page_policy];
GO
CREATE VIEW [dbo].[exf_page_policy] (
	[page_oid],
   	[authorization_policy_oid]
) AS 
	SELECT 
	  p.oid AS page_oid, 
 	  apol.oid AS authorization_policy_oid
	FROM (((dbo.exf_page  AS p 
	  LEFT JOIN dbo.exf_page_group_pages  AS pgp ON pgp.page_oid = p.oid) 
	  LEFT JOIN dbo.exf_auth_policy  AS apol ON apol.target_page_group_oid = pgp.page_group_oid) 
	  INNER JOIN dbo.exf_auth_point  AS apt ON apol.auth_point_oid = apt.oid)
	WHERE (apt.target_page_group_applicable = 1)
	UNION ALL
	SELECT 
	  p.oid AS page_oid, 
	  apol.oid AS authorization_policy_oid
	FROM ((dbo.exf_page  AS p 
	  LEFT JOIN dbo.exf_auth_policy  AS apol 
	  ON (
		 CASE 
			WHEN CAST(apol.target_page_group_oid AS varchar(max)) IS NULL THEN 1
			ELSE 0
		 END <> 0)) 
	  INNER JOIN dbo.exf_auth_point  AS apt 
	  ON ((apol.auth_point_oid = apt.oid)))
	WHERE (apt.target_page_group_applicable = 1);