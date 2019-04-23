UPDATE modx_site_content SET content = REPLACE(content, '"EditMatrix"', '"DataSpreadSheet"');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, '"EditMatrix"', '"DataSpreadSheet"');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, '"EditMatrix"', '"DataSpreadSheet"');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, '"EditMatrix"', '"DataSpreadSheet"');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, '"EditMatrix"', '"DataSpreadSheet"');

UPDATE modx_site_content SET content = REPLACE(content, '"editMatrix"', '"DataSpreadSheet"');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, '"editMatrix"', '"DataSpreadSheet"');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, '"editMatrix"', '"DataSpreadSheet"');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, '"editMatrix"', '"DataSpreadSheet"');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, '"editMatrix"', '"DataSpreadSheet"');