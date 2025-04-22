<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for UXON schema names: widget, action, datatype, etc.
 * 
 * @method UxonSchemaDataType GENERIC(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaDataType WIDGET(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaDataType ACTION(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaDataType DATATYPE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaDataType BEHAVIOR(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonSchemaDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const GENERIC = "generic";
    const WIDGET = "widget";
    const ACTION = "action";
    const DATATYPE = "datatype";
    const BEHAVIOR = "behavior";
    const CONNECTION = "connection";
    const FACADE = "facade";
    const QUERYBUILDER = 'querybuilder';
    const QUERYBUILDER_ATTRIBUTE = 'querybuilder_attribute';
    const QUERYBUILDER_OBJECT = 'querybuilder_object';
    const SNIPPET = 'snippet';

    private static $labels = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty(static::$labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $const => $val) {
                static::$labels[$val] = $translator->translate('GLOBAL.UXON.SCHEMA.' . $const);
            }
        }
        
        return static::$labels;
    }
}