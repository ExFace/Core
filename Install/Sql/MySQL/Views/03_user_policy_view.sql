CREATE OR REPLACE VIEW exf_user_policy AS
/* Policies of explicitly assigned roles */
SELECT 
	u.oid AS user_oid,
	apol.oid AS authorization_policy_oid
FROM exf_user u
	LEFT JOIN exf_user_role_users uru ON uru.user_oid = u.oid
	LEFT JOIN exf_auth_policy apol ON apol.target_user_role_oid = uru.user_role_oid
	INNER JOIN exf_auth_point apt ON apol.auth_point_oid = apt.oid
WHERE 
	apt.target_user_role_applicable = 1
UNION ALL
/* Policies with no role or the exface.Core.AUTHENTICATED role assigned to all users */
SELECT 
	u.oid AS user_oid,
	apol.oid AS authorization_policy_oid
FROM exf_user u
	LEFT JOIN exf_auth_policy apol ON apol.target_user_role_oid IS NULL OR (apol.target_user_role_oid = 0x11ea6fa3cab9a380a3480205857feb80 AND u.oid != 0x00000000000000000000000000000000)
	INNER JOIN exf_auth_point apt ON apol.auth_point_oid = apt.oid
WHERE 
	apt.target_user_role_applicable = 1