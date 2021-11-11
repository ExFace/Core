-- UP

UPDATE exf_attribute SET data_properties = REPLACE(data_properties, 'SQL_USE_OPTIMIZED_UID', 'SQL_INSERT_UUID_OPTIMIZED');
	
-- DOWN

UPDATE exf_attribute SET data_properties = REPLACE(data_properties, 'SQL_INSERT_UUID_OPTIMIZED', 'SQL_USE_OPTIMIZED_UID');