UPDATE exf_object_action SET `action` = REPLACE(`action`, 'ShowObjectDialog', 'ShowObjectInfoDialog');
UPDATE exf_object_action SET `action` = REPLACE(`action`, 'EditObjectDialog', 'ShowObjectEditDialog');
UPDATE exf_object_action SET `action` = REPLACE(`action`, 'CreateObjectDialog', 'ShowObjectCreateDialog');
UPDATE exf_object_action SET `action` = REPLACE(`action`, 'DuplicateObjectDialog', 'ShowObjectCopyDialog');
UPDATE exf_object_action SET `action` = REPLACE(`action`, 'MassObjectDialog', 'ShowMassEditDialog');