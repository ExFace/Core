<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\UxonSchemaDataType;

/**
 * UXON-schema class for meta object behaviors.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class BehaviorSchema extends UxonSchema
{
    public static function getSchemaName() : string
    {
        return UxonSchemaDataType::BEHAVIOR;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractBehavior::class;
    }
}