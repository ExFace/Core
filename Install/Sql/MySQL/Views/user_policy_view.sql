CREATE OR REPLACE VIEW user_policy_view AS
SELECT 
	u.oid AS user_oid,
	uru.user_role_oid AS user_role_oid,
	apol.*
FROM exf_user u
	LEFT JOIN exf_user_role_users uru ON uru.user_oid = u.oid
	LEFT JOIN exf_auth_policy apol ON apol.target_user_role_oid = uru.user_role_oid OR apol.target_user_role_oid IS NULL
	INNER JOIN exf_auth_point apt ON apol.auth_point_oid = apt.oid
WHERE 
	apt.target_user_role_applicable = 1;