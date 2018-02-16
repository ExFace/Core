<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of comparators: =, ==, <, >, etc.
 * 
 * @method ComparatorsDataType IN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType NOT_IN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType IS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType IS_NOT(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType EQUALS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType EQUALS_NOT(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LESS_THAN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LESS_THAN_OR_EQUALS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType GREATER_THAN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType GREATER_THAN_OR_EQUALS(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class ComparatorDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    /**
     * @const IN compares to each vaule in a list via IS.
     * At least one must suffice.
     */
    const IN = '[';
    
    const NOT_IN = '![';
    
    /**
     * @const IS universal comparater - can be applied to any data type
     */
    const IS = '=';
    
    const IS_NOT = '!=';
    
    /**
     * @const EQUALS compares to a single value of the same data type
     */
    const EQUALS = '==';
    
    const EQUALS_NOT = '!==';
    
    const LESS_THAN = '<';
    
    const LESS_THAN_OR_EQUALS = '<=';
    
    const GREATER_THAN = '>';
    
    const GREATER_THAN_OR_EQUALS = '>=';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $val) {
                $this->labels[$val] = $translator->translate('GLOBAL.COMPARATOR.' . static::findKey($val));
            }
        }
        
        return $this->labels;
    }

}
?>