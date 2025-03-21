<?php
namespace exface\Core\Uxon;

use exface\Core\DataTypes\UxonSchemaDataType;
use exface\Core\CommonLogic\AbstractDataConnector;

/**
 * UXON-schema class for data connections.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class ConnectionSchema extends UxonSchema
{

    public static function getSchemaName() : string
    {
        return UxonSchemaDataType::CONNECTION;
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractDataConnector::class;
    }
}