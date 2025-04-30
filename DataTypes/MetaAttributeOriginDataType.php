<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for attribut originis: direct attribute, inherited or generated
 * 
 * @method MetaAttributeOriginDataType DIRECT_ATTRIBUTE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MetaAttributeOriginDataType INHERITED_ATTRIBUTE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MetaAttributeOriginDataType CUSTOM_ATTRIBUTE(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaAttributeOriginDataType extends NumberDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const DIRECT_ATTRIBUTE = 1;
    const INHERITED_ATTRIBUTE = 2;
    const CUSTOM_ATTRIBUTE = 3;

    private $labels = [];
    
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
                $this->labels[$val] = $translator->translate('GLOBAL.MODEL.ATTRIBUTE_ORIGIN.' . $const);
            }
        }
        
        return $this->labels;
    }
}