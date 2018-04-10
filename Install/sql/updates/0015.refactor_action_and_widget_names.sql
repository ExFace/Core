UPDATE modx_site_content SET content = REPLACE(content, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE modx_site_content SET content = REPLACE(content, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE modx_site_content SET content = REPLACE(content, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE modx_site_content SET content = REPLACE(content, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE modx_site_content SET content = REPLACE(content, 'MassObjectDialog', 'ShowMassEditDialog');

UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, 'MassObjectDialog', 'ShowMassEditDialog');

UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, 'MassObjectDialog', 'ShowMassEditDialog');

UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, 'MassObjectDialog', 'ShowMassEditDialog');

UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, 'MassObjectDialog', 'ShowMassEditDialog');



UPDATE modx_site_content SET content = REPLACE(content, '"CheckBox"', '"InputCheckBox"');
UPDATE modx_site_content SET content = REPLACE(content, '"ComboTable"', '"InputComboTable"');
UPDATE modx_site_content SET content = REPLACE(content, '"ImageSlider"', '"ImageCarousel"');

UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, '"CheckBox"', '"InputCheckBox"');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, '"ComboTable"', '"InputComboTable"');
UPDATE exf_object SET default_editor_uxon = REPLACE(default_editor_uxon, '"ImageSlider"', '"ImageCarousel"');

UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, '"CheckBox"', '"InputCheckBox"');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, '"ComboTable"', '"InputComboTable"');
UPDATE exf_attribute SET default_editor_uxon = REPLACE(default_editor_uxon, '"ImageSlider"', '"ImageCarousel"');

UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, '"CheckBox"', '"InputCheckBox"');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, '"ComboTable"', '"InputComboTable"');
UPDATE exf_data_type SET default_editor_uxon = REPLACE(default_editor_uxon, '"ImageSlider"', '"ImageCarousel"');

UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, '"CheckBox"', '"InputCheckBox"');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, '"ComboTable"', '"InputComboTable"');
UPDATE exf_object_action SET config_uxon = REPLACE(config_uxon, '"ImageSlider"', '"ImageCarousel"');