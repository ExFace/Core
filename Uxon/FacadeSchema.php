<?php
namespace exface\Core\Uxon;

use exface\Core\DataTypes\UxonSchemaDataType;
use exface\Core\Facades\AbstractFacade\AbstractFacade;

/**
 * UXON-schema class for facade configuration.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadeSchema extends UxonSchema
{

    public static function getSchemaName() : string
    {
        return UxonSchemaDataType::FACADE;
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractFacade::class;
    }
}