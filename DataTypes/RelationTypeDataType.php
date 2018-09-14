<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of relation types: 1n, 11, n1.
 * 
 * @method RelationTypeDataType REGULAR(\exface\Core\CommonLogic\Workbench $workbench)
 * @method RelationTypeDataType REVERSE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method RelationTypeDataType ONE_TO_ONE(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class RelationTypeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const REGULAR = "N1";
    const REVERSE = "1N";
    const ONE_TO_ONE = "11";
    
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
                $this->labels[$val] = $translator->translate('GLOBAL.MODEL.RELATION_TYPE.' . $const);
            }
        }
        
        return $this->labels;
    }

}
?>