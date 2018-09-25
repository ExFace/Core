update exf_object_behaviors set config_uxon = REPLACE(config_uxon, '"object_event_alias":"DataSheet.UpdateData.Before"', '"event_alias":"exface.Core.DataSheet.OnBeforeUpdateData"');
update exf_object_behaviors set config_uxon = REPLACE(config_uxon, '"object_event_alias":"DataSheet.CreateData.After"', '"event_alias":"exface.Core.DataSheet.OnCreateData"');
update exf_object_behaviors set config_uxon = REPLACE(config_uxon, '"object_event_alias":"DataSheet.DeleteData.Before"', '"event_alias":"exface.Core.DataSheet.OnBeforeDeleteData"');
