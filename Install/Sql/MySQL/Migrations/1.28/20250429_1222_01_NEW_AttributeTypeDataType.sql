/*
This migration switches the data type exface.Core.AttributeType to the new dedicated
enum prototype. It is important to do it via SQL migration because the migration will be
performed BEFORE the model installer is run. If the model installer will attempt to
install an attribute with a new type (like `G`) on an old installation, that does not
have that type yet, it will fail to install the Core completely. Switching to the
dedicated prototype ensures, any new types are there already because the code is the
first thing, that is being updated.
*/

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

