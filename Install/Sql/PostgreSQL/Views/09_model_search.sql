CREATE OR REPLACE VIEW exf_model_search 
AS

/* Object: Default editor */
SELECT 
	o.default_editor_uxon AS uxon,
	'Object' AS object_name,
	'Default editor' AS attribute_name,
	o.object_name AS instance_name,
	o.object_alias AS instance_alias,
	o.oid AS oid,
	'exf_object' AS "table_name",
	o.app_oid
FROM exf_object o
WHERE o.default_editor_uxon IS NOT NULL

UNION ALL
/* Object: Data address props */
SELECT 
	o.data_address_properties AS uxon,
	'Object' AS object_name,
	'Data Source Settings' AS attribute_name,
	o.object_name AS instance_name,
	o.object_alias AS instance_alias,
	o.oid AS oid,
	'exf_object' AS "table_name",
	o.app_oid
FROM exf_object o
WHERE o.data_address_properties IS NOT NULL

UNION ALL
/* Object: Data address */
SELECT
    o.data_address AS uxon,
    'Object' AS object_name,
    'Data address' AS attribute_name,
    o.object_name AS instance_name,
    o.object_alias AS instance_alias,
    o.oid AS oid,
    'exf_object' AS "table_name",
    o.app_oid
FROM exf_object o
WHERE o.data_address IS NOT NULL

UNION ALL
/* Attribute: Data Address */
SELECT
    a.data,
    'Attribute' AS object_name,
    'Data Address' AS attribute_name,
    a.attribute_name AS instance_name,
    a.attribute_alias AS instance_alias,
    a.oid AS oid,
    'exf_attribute' AS "table_name",
    ao.app_oid
FROM exf_attribute a
         INNER JOIN exf_object ao ON ao.oid = a.object_oid
WHERE a.data IS NOT NULL

UNION ALL
/* Attribute: Data Address Settings */
SELECT 
	a.data_properties,
	'Attribute' AS object_name,
	'Data Address Settings' AS attribute_name,
	a.attribute_name AS instance_name,
	a.attribute_alias AS instance_alias,
	a.oid AS oid,
	'exf_attribute' AS "table_name",
	ao.app_oid
FROM exf_attribute a
	INNER JOIN exf_object ao ON ao.oid = a.object_oid
WHERE a.data_properties IS NOT NULL

UNION ALL
/* Attribute: default editor */
SELECT 
	a.default_editor_uxon,
	'Attribute' AS object_name,
	'Default editor' AS attribute_name,
	a.attribute_name AS instance_name,
	a.attribute_alias AS instance_alias,
	a.oid AS oid,
	'exf_attribute' AS "table_name",
	ao.app_oid
FROM exf_attribute a
	INNER JOIN exf_object ao ON ao.oid = a.object_oid
WHERE a.default_editor_uxon IS NOT NULL

UNION ALL
/* Attribute: default display */
SELECT 
	a.default_display_uxon,
	'Attribute' AS object_name,
	'Default display' AS attribute_name,
	a.attribute_name AS instance_name,
	a.attribute_alias AS instance_alias,
	a.oid AS oid,
	'exf_attribute' AS "table_name",
	ao.app_oid
FROM exf_attribute a
	INNER JOIN exf_object ao ON ao.oid = a.object_oid
WHERE a.default_display_uxon IS NOT NULL

UNION ALL
/* Attribute: data type */
SELECT 
	a.custom_data_type_uxon,
	'Attribute' AS object_name,
	'Data type config' AS attribute_name,
	a.attribute_name AS instance_name,
	a.attribute_alias AS instance_alias,
	a.oid AS oid,
	'exf_attribute' AS "table_name",
	ao.app_oid
FROM exf_attribute a
	INNER JOIN exf_object ao ON ao.oid = a.object_oid
WHERE a.custom_data_type_uxon IS NOT NULL

UNION ALL
/* Data type: config */
SELECT 
	dt.config_uxon,
	'Data type' AS object_name,
	'Configuration' AS attribute_name,
	dt.name AS instance_name,
	dt.data_type_alias AS instance_alias,
	dt.oid AS oid,
	'exf_data_type' AS "table_name",
	dt.app_oid
FROM exf_data_type dt
WHERE dt.config_uxon IS NOT NULL

UNION ALL
/* Data type: default edtor */
SELECT 
	dt.default_editor_uxon,
	'Data type' AS object_name,
	'Default editor' AS attribute_name,
	dt.name As instance_name,
	dt.data_type_alias AS instance_alias,
	dt.oid AS oid,
	'exf_data_type' AS "table_name",
	dt.app_oid
FROM exf_data_type dt
WHERE dt.default_editor_uxon IS NOT NULL

UNION ALL
/* Data type: default display */
SELECT 
	dt.default_display_uxon,
	'Data type' AS object_name,
	'Default display' AS attribute_name,
	dt.name As instance_name,
	dt.data_type_alias AS instance_alias,
	dt.oid AS oid,
	'exf_data_type' AS "table_name",
	dt.app_oid
FROM exf_data_type dt
WHERE dt.default_display_uxon IS NOT NULL

UNION ALL
/* Behavior: config */
SELECT 
	ob.config_uxon,
	'Behavior' AS object_name,
	'Configuration' AS attribute_name,
	ob.name AS instance_name,
	NULL AS instance_alias,
	ob.oid AS oid,
	'exf_object_behaviors' AS "table_name",
	ob.behavior_app_oid AS app_oid
FROM exf_object_behaviors ob
WHERE ob.config_uxon IS NOT NULL

UNION ALL
/* Action: config */
SELECT 
	oa.config_uxon,
	'Action' AS object_name,
	'Configuration' AS attribute_name,
	oa.name AS instance_name,
	oa.alias AS instance_alias,
	oa.oid AS oid,
	'exf_object_action' AS "table_name",
	oa.action_app_oid AS app_oid
FROM exf_object_action oa
WHERE oa.config_uxon IS NOT NULL

UNION ALL
/* Page: widget */
SELECT 
	p.content,
	'Page' AS object_name,
	'Widget' AS attribute_name,
	p.name AS instance_name,
	p.alias AS instance_alias,
	p.oid AS oid,
	'exf_page' AS "table_name",
	p.app_oid
FROM exf_page p
WHERE p.content IS NOT NULL

UNION ALL
/* UXON preset: UXON */
SELECT 
	ps.uxon,
	'UXON Preset' AS object_name,
	'UXON' AS attribute_name,
	ps.name AS instance_name,
	NULL AS instance_alias,
	ps.oid AS oid,
	'exf_uxon_preset' AS "table_name",
	ps.app_oid
FROM exf_uxon_preset ps
WHERE ps.uxon IS NOT NULL

UNION ALL
/* Communication channel: default message */
SELECT 
	cc.message_default_uxon,
	'Communication channel' AS object_name,
	'Default message' AS attribute_name,
	cc.name AS instance_name,
	cc.alias AS instance_alias,
	cc.oid AS oid,
	'exf_communication_channel' AS "table_name",
	cc.app_oid
FROM exf_communication_channel cc
WHERE cc.message_default_uxon IS NOT NULL

UNION ALL
/* Communication template: config */
SELECT 
	ct.message_uxon,
	'Communication template' AS object_name,
	'Message' AS attribute_name,
	ct.name AS instance_name,
	ct.alias AS instance_alias,
	ct.oid AS oid,
	'exf_communication_template' AS "table_name",
	ct.app_oid
FROM exf_communication_template ct
WHERE ct.message_uxon IS NOT NULL

UNION ALL
/* Data connection: config */
SELECT 
	dc.data_connector_config,
	'Data connection' AS object_name,
	'Configuration' AS attribute_name,
	dc.name AS instance_name,
	dc.alias AS instance_alias,
	dc.oid AS oid,
	'exf_data_connection' AS "table_name",
	dc.app_oid
FROM exf_data_connection dc
WHERE dc.data_connector_config IS NOT NULL

UNION ALL
/* Scheduler: action UXON */
SELECT 
	sca.action_uxon,
	'Scheduler' AS object_name,
	'Action configuration' AS attribute_name,
	sca.name AS instance_name,
	NULL AS instance_alias,
	sca.oid AS oid,
	'exf_scheduler' AS "table_name",
	sca.app_oid
FROM exf_scheduler sca
WHERE sca.action_uxon IS NOT NULL

UNION ALL
/* Scheduler: task UXON */
SELECT 
	sct.task_uxon,
	'Scheduler' AS object_name,
	'Task configuration' AS attribute_name,
	sct.name AS instance_name,
	NULL AS instance_alias,
	sct.oid AS oid,
	'exf_scheduler' AS "table_name",
	sct.app_oid
FROM exf_scheduler sct
WHERE sct.task_uxon IS NOT NULL

UNION ALL
/* Snippets: snippet UXON */
SELECT
    usn.uxon,
    'UXON snippet' AS object_name,
    'Snippet' AS attribute_name,
    usn.name AS instance_name,
    usn.ALIAS AS instance_alias,
    usn.oid AS oid,
    'exf_uxon_snippet' AS "table_name",
    usn.app_oid
FROM exf_uxon_snippet usn

UNION ALL
/* Mutations: mutation UXON */
SELECT
    mu.config_uxon,
    'Mutation' AS object_name,
    'Mutation config' AS attribute_name,
    mu.name AS instance_name,
    NULL AS instance_alias,
    mu.oid AS oid,
    'exf_mutation' AS "table_name",
    mus.app_oid
FROM exf_mutation mu
    INNER JOIN exf_mutation_set mus ON mus.oid = mu.mutation_set_oid