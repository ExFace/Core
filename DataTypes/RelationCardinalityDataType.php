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

    /**
     * @param string $cardinality
     * @return string
     */
    public static function findCardinalityOfReverseRelation(string $cardinality) : string
    {
        switch ($cardinality) {
            case 'NM':
                $cardinality = RelationCardinalityDataType::N_TO_M;
                break;
            case '11':
                $cardinality = RelationCardinalityDataType::ONE_TO_ONE;
                break;
            case '1N':
                $cardinality = RelationCardinalityDataType::N_TO_ONE;
                break;
            default:
                // An regular n-to-1 relation pointing to our attribute is a reversed one (1-to-n)
                // from it's point of view.
                $cardinality = RelationCardinalityDataType::ONE_TO_N;
        }
        return $cardinality;
    }

}
?>