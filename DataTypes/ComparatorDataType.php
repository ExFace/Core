<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of comparators: `=`, `==`, `<`, `>`, etc.
 * 
 * - `=` - universal comparator similar to SQL's `LIKE` with % on both sides. Can compare different 
 * data types. If the left value is a string, becomes TRUE if it contains the right value. Case 
 * insensitive for strings
 * - `!=` - yields TRUE if `IS` would result in FALSE
 * - `==` - compares two single values of the same type. Case sensitive for stings. Normalizes the 
 * values before comparison though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
 * - `!==` - the inverse of `EQUALS`
 * - `[` - IN-comparator - compares to each vaule in a list via EQUALS. Becomes true if the left
 * value equals at least on of the values in the list within the right value. The list on the
 * right side must consist of numbers or strings separated by commas or the attribute's value
 * list delimiter if filtering over an attribute. The right side can also be another type of
 * expression (e.g. a formula or widget link), that yields such a list.
 * - `![` - the inverse von `[` . Becomes true if the left value equals none of the values in the 
 * list within the right value. The list on the right side must consist of numbers or strings separated 
 * by commas or the attribute's value list delimiter if filtering over an attribute. The right side can 
 * also be another type of expression (e.g. a formula or widget link), that yields such a list.
 * - `<` - yields TRUE if the left value is less than the right one. Both values must be of
 * comparable types: e.g. numbers or dates.
 * - `<=` - yields TRUE if the left value is less than or equal to the right one. 
 * Both values must be of comparable types: e.g. numbers or dates.
 * - `>` - yields TRUE if the left value is greater than the right one. Both values must be of
 * comparable types: e.g. numbers or dates.
 * - `>=` - yields TRUE if the left value is greater than or equal to the right one. 
 * Both values must be of comparable types: e.g. numbers or dates.
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
 * @method ComparatorsDataType BETWEEN(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class ComparatorDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    /**
     * @const IN compares to each vaule in a list via EQUALS. Becomes true if the left
     * value equals at leas on of the values in the list within the right value.
     */
    const IN = '[';
    
    /**
     * @const NOT_IN the inverse von `[` . Becomes true if the left value equals none of the values in the 
     * list within the right value.
     */
    const NOT_IN = '![';
    
    /**
     * @const IS universal comparator similar to SQL's `LIKE`. Can compare different data types.
     * If the left value is a string, becomes TRUE if it contains the right value. Case insensitive
     * for strings.
     */
    const IS = '=';
    
    /**
     * 
     * @const IS_NOT yields TRUE if `IS` would result in FALSE
     */
    const IS_NOT = '!=';
    
    /**
     * @const EQUALS compares two single values of the same type. Normalizes the values before comparison
     * though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
     */
    const EQUALS = '==';
    
    /**
     * 
     * @const EQUALS_NOT the opposite of `EQUALS`.
     */
    const EQUALS_NOT = '!==';
    
    /**
     * 
     * @const LESS_THAN yields TRUE if the left value is less than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     */
    const LESS_THAN = '<';
    
    /**
     *
     * @const LESS_THAN_OR_EQUALS yields TRUE if the left value is less than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     */
    const LESS_THAN_OR_EQUALS = '<=';
    
    /**
     *
     * @const GREATER_THAN yields TRUE if the left value is greater than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     */
    const GREATER_THAN = '>';
    
    /**
     *
     * @const GREATER_THAN_OR_EQUALS yields TRUE if the left value is greater than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     */
    const GREATER_THAN_OR_EQUALS = '>=';
    
    const BETWEEN = '..';
    
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