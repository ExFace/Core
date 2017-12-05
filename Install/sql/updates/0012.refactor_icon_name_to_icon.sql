update exf_object set default_editor_uxon = REPLACE(default_editor_uxon,'"icon_name"','"icon"');
update exf_attribute set default_editor_uxon = REPLACE(default_editor_uxon,'"icon_name"','"icon"');
update exf_object_action set config_uxon = REPLACE(config_uxon,'"icon_name"','"icon"');
update exf_object_behaviors set config_uxon = REPLACE(config_uxon,'"icon_name"','"icon"');