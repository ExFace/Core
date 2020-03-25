CREATE OR REPLACE VIEW exf_page_policy AS
SELECT 
	p.oid AS page_oid,
	pgp.page_group_oid AS page_group_oid,
	apol.*
FROM exf_page p
	LEFT JOIN exf_page_group_pages pgp ON pgp.page_oid = p.oid
	LEFT JOIN exf_auth_policy apol ON apol.target_page_group_oid = pgp.page_group_oid OR apol.target_page_group_oid IS NULL
	INNER JOIN exf_auth_point apt ON apol.auth_point_oid = apt.oid
WHERE 
	apt.target_page_group_applicable = 1;