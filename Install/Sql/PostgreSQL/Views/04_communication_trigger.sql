CREATE OR REPLACE VIEW exf_communication_trigger AS
SELECT
	ct.oid AS communication_template_oid,
	ob.oid AS object_behavior_oid,
	oa.oid AS object_action_oid,
	(CASE
		WHEN ob.oid IS NOT NULL THEN ob.object_oid
		WHEN oa.oid IS NOT NULL THEN oa.object_oid
		ELSE NULL
	END) AS object_oid,
	(CASE
		WHEN ob.oid IS NOT NULL THEN ob.name
		WHEN oa.oid IS NOT NULL THEN oa.name
		ELSE NULL
	END) AS name,
	(CASE
		WHEN ob.oid IS NOT NULL THEN 'object_behavior'
		WHEN oa.oid IS NOT NULL THEN 'object_action'
		ELSE NULL
	END) AS entity
	FROM exf_communication_template ct
		LEFT JOIN exf_app a ON a.oid = ct.app_oid
		LEFT JOIN exf_object_behaviors ob ON ob.config_uxon LIKE CONCAT('%', (CASE WHEN ct.app_oid IS NOT NULL THEN CONCAT(a.app_alias, '.') ELSE '' END), ct.alias, '%')
		LEFT JOIN exf_object_action oa ON oa.config_uxon LIKE CONCAT('%', (CASE WHEN ct.app_oid IS NOT NULL THEN CONCAT(a.app_alias, '.') ELSE '' END), ct.alias, '%')