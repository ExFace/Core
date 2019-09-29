<?php
namespace exface\Core\Uxon;

use exface\Core\DataTypes\UxonSchemaNameDataType;
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
        return UxonSchemaNameDataType::CONNECTION;
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractDataConnector::class;
    }
}