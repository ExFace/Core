<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration attribute types: data attributes, compounds, calculated or runtime generated attribtues
 * 
 * @method MetaAttributeTypeDataType DATA(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MetaAttributeTypeDataType COMPOUND(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MetaAttributeTypeDataType CALCULATED(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MetaAttributeTypeDataType GENERATED(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaAttributeTypeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const DATA = "D";
    const COMPOUND = "C";
    const CALCULATED = "X";
    const GENERATED = "G";

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
                $this->labels[$val] = $translator->translate('GLOBAL.MODEL.ATTRIBUTE_TYPE.' . $const);
            }
        }
        
        return $this->labels;
    }
}