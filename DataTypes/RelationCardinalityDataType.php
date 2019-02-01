<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of relation cardinalities: 1n, 11, n1, nm.
 * 
 * @method RelationCardinalityDataType N_TO_ONE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method RelationCardinalityDataType ONE_TO_N(\exface\Core\CommonLogic\Workbench $workbench)
 * @method RelationCardinalityDataType ONE_TO_ONE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method RelationCardinalityDataType N_TO_M(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class RelationCardinalityDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const N_TO_ONE = "N1";
    const ONE_TO_N = "1N";
    const ONE_TO_ONE = "11";
    const N_TO_M = "NM";
    
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
                $this->labels[$val] = $translator->translate('GLOBAL.MODEL.RELATION_CARDINALITY.' . $const);
            }
        }
        
        return $this->labels;
    }

}
?>