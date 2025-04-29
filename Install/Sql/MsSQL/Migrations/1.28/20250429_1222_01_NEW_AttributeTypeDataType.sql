-- UP

UPDATE exf_data_type SET 
    prototype = 'exface/Core/DataTypes/MetaAttributeTypeDataType.php', 
    config_uxon = '{}' 
WHERE oid = 0x11ea438c00f52350bb290205857feb80;

-- DOWN

UPDATE exf_data_type SET 
    prototype = 'exface/Core/DataTypes/StringEnumDataType.php', 
    config_uxon = '{}' 
WHERE oid = 0x11ea438c00f52350bb290205857feb80;

