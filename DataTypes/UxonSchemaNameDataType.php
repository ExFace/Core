<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for UXON schema names: widget, action, datatype, etc.
 * 
 * @method UxonSchemaNameDataType GENERIC(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaNameDataType WIDGET(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaNameDataType ACTION(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaNameDataType DATATYPE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method UxonSchemaNameDataType BEHAVIOR(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonSchemaNameDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const GENERIC = "generic";
    const WIDGET = "widget";
    const ACTION = "action";
    const DATATYPE = "datatype";
    const BEHAVIOR = "behavior";
    const CONNECTION = "connection";
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $const => $val) {
                $this->labels[$val] = $translator->translate('GLOBAL.UXON.SCHEMA.' . $const);
            }
        }
        
        return $this->labels;
    }
}
?>