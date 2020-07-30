IF OBJECT_ID('dbo.exf_relation_tree', 'V') IS NOT NULL
    DROP VIEW [dbo].[exf_relation_tree];

/* List of all attributes, that are relations with their relation aliases, directions, etc. */
CREATE VIEW [dbo].[exf_relation_tree] (
	[oid], 
	[attribute_oid],
	[created_on],
	[modified_on], 
	[created_by_user_oid], 
	[modified_by_user_oid], 
	[attribute_name],
	[object_oid], 
	[related_object_oid], 
	[related_object_special_key_attribute_oid], 
	[left_object_oid], 
	[relation_type], 
	[right_object_oid], 
	[relation_alias], 
	[relation_name], 
	[relation_alias_modifier],
	[unambiguous_relation_flag],
	[only_required_reverse_relation_flag],
	[relation_alias_full],
	[relation_alias_short],
	[relation_name_short])
AS 
(
SELECT 
    rt.*,
    CONCAT(rt.relation_alias,rt.relation_alias_modifier) AS relation_alias_full,
    CASE WHEN rt.unambiguous_relation_flag = 0 AND rt.only_required_reverse_relation_flag = 0 THEN CONCAT(rt.relation_alias, rt.relation_alias_modifier) ELSE rt.relation_alias END AS relation_alias_short,
    CASE WHEN rt.unambiguous_relation_flag = 0 AND rt.only_required_reverse_relation_flag = 0 THEN CONCAT(rt.relation_name, ' (', rt.attribute_name, ')') ELSE rt.relation_name END AS relation_name_short
FROM (
	SELECT 
		CONCAT(LOWER(CONVERT(VARCHAR(34), a.oid, 1)), LOWER(CONVERT(VARCHAR(34), a.object_oid, 1))) AS oid,  
		a.oid as attribute_oid,
		a.created_on,
		a.modified_on, 
		a.created_by_user_oid, 
		a.modified_by_user_oid, 
		a.attribute_name,
		a.object_oid, 
		a.related_object_oid, 
		a.related_object_special_key_attribute_oid, 
		a.object_oid as left_object_oid, 
		'regular'as relation_type, 
		a.related_object_oid as right_object_oid, 
		a.attribute_alias as relation_alias, 
		a.attribute_name as relation_name, 
		'' as relation_alias_modifier,
		1 as unambiguous_relation_flag,
		0 as only_required_reverse_relation_flag
	FROM exf_attribute a 
		LEFT JOIN exf_object ao ON ao.oid = a.object_oid
		LEFT JOIN exf_object ro ON ro.oid = a.related_object_oid
	WHERE 
		a.related_object_oid IS NOT NULL
		
	UNION ALL
	
	SELECT 
		CONCAT(LOWER(CONVERT(VARCHAR(18), ar.oid, 1)), LOWER(CONVERT(VARCHAR(18), ar.related_object_oid, 1))) AS oid,  
		ar.oid as attribute_oid,
		ar.created_on, 
		ar.modified_on, 
		ar.created_by_user_oid, 
		ar.modified_by_user_oid, 
		ar.attribute_name,
		ar.object_oid, 
		ar.related_object_oid, 
		ar.related_object_special_key_attribute_oid, 
		ar.related_object_oid as left_object_oid, 
		'reverse' as relation_type, 
		ar.object_oid as right_object_oid, 
		aor.object_alias as relation_alias, 
		aor.object_name as relation_name, 
		CONCAT('[', ar.attribute_alias, ']') as relation_alias_modifier,
		CASE 
			WHEN (SELECT COUNT(*) FROM exf_attribute WHERE related_object_oid = ar.related_object_oid AND object_oid = ar.object_oid AND oid <> ar.oid) > 0
			THEN 0
			ELSE 1
		END AS unambiguous_relation_flag,
		CASE
			WHEN ar.attribute_required_flag = 1 AND (SELECT COUNT(*) FROM exf_attribute WHERE related_object_oid = ar.related_object_oid AND object_oid = ar.object_oid AND oid <> ar.oid AND attribute_required_flag = 1) = 0
			THEN 1
			ELSE 0
		END AS only_required_reverse_relation_flag
	FROM exf_attribute ar 
		LEFT JOIN exf_object aor ON aor.oid = ar.object_oid
		LEFT JOIN exf_object ror ON ror.oid = ar.related_object_oid
	WHERE 
		ar.related_object_oid IS NOT NULL 
	) rt
)