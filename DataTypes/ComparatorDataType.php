<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Interfaces\Model\ConditionInterface;

/**
 * Logical comparison operators: `=`, `==`, `<`, `>`, etc.
 * 
 * ## Scalar (single value) comparators
 * 
 * - `=` - universal comparator similar to SQL's `LIKE` with % on both sides. Can compare different 
 * data types. If the left value is a string, becomes TRUE if it contains the right value. Case 
 * insensitive for strings
 * - `!=` - yields TRUE if `IS` would result in FALSE
 * - `==` - compares two single values of the same type. Case sensitive for stings. Normalizes the 
 * values before comparison though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
 * - `!==` - the inverse of `EQUALS`
 * - `<` - yields TRUE if the left value is less than the right one. Both values must be of
 * comparable types: e.g. numbers or dates.
 * - `<=` - yields TRUE if the left value is less than or equal to the right one. 
 * Both values must be of comparable types: e.g. numbers or dates.
 * - `>` - yields TRUE if the left value is greater than the right one. Both values must be of
 * comparable types: e.g. numbers or dates.
 * - `>=` - yields TRUE if the left value is greater than or equal to the right one. 
 * Both values must be of comparable types: e.g. numbers or dates.
 * 
 * ## List comparators
 * 
 * - `[` - IN-comparator - compares a value with each item in a list via EQUALS. Becomes true if the left
 * value equals at least on of the values in the list within the right value. The list on the
 * right side must consist of numbers or strings separated by commas or the attribute's value
 * list delimiter if filtering over an attribute. The right side can also be another type of
 * expression (e.g. a formula or widget link), that yields such a list.
 * - `![` - the inverse von `[` . Becomes true if the left value equals none of the values in the 
 * list within the right value. The list on the right side must consist of numbers or strings separated 
 * by commas or the attribute's value list delimiter if filtering over an attribute. The right side can 
 * also be another type of expression (e.g. a formula or widget link), that yields such a list.
 * - `][` - intersection - compares two lists with each other. Becomes TRUE when there is at least 
 * one element, that is present in both lists.
 * - `!][` - the inverse of `][`. Becomes TRUE if no element is part of both lists.
 * - `[[` - subset - compares two lists with each other. Becomes true when all elements of the left list 
 * are in the right list too
 * - `![[` - the inverse of `][`. Becomes true when at least one element of the left list is NOT in 
 * the right list.
 * 
 * ## Range comparators
 * 
 * - `..` - range between two values - e.g. `1 .. 5`
 * 
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
 * @method ComparatorsDataType IN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType NOT_IN(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LIST_INTERSECTS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LIST_NOT_INTERSECTS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LIST_SUBSET(\exface\Core\CommonLogic\Workbench $workbench)
 * @method ComparatorsDataType LIST_NOT_SUBSET(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class ComparatorDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
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
     * @const LIST_INTERSECTS compares two lists with each other. Becomes true when at least one element is present in
     *     both lists.
     */
    const LIST_INTERSECTS = '][';
    
    /**
     * @const LIST_NOT_INTERSECTS the inverse of `][`. Becomes true when no element is present in both
     *     lists.
     */
    const LIST_NOT_INTERSECTS = '!][';
    
    /**
     * @const LIST_SUBSET compares two lists with each other. Becomes true when all elements of the left list are
     *     present in the right list.
     */
    const LIST_SUBSET = '[[';
    
    /**
     * @const LIST_NOT_SUBSET the inverse of `[[`. Becomes true when at least one element of the left list is NOT
     *     present in the right list.
     */
    const LIST_NOT_SUBSET = '![[';
    
    const LIST_EACH_IS = '[=';
    
    const LIST_EACH_IS_NOT = '[!=';
    
    const LIST_EACH_EQUALS = '[==';
    
    const LIST_EACH_EQUALS_NOT = '[!==';
    
    const LIST_EACH_LESS_THAN = '[<';
    
    const LIST_EACH_LESS_THAN_OR_EQUALS = '[<=';
    
    const LIST_EACH_GREATER_THAN = '[>';
    
    const LIST_EACH_GREATER_THAN_OR_EQUALS = '[>=';
    
    const LIST_ANY_IS = ']=';
    
    const LIST_ANY_IS_NOT = ']!=';
    
    const LIST_ANY_EQUALS = ']==';
    
    const LIST_ANY_EQUALS_NOT = ']!==';
    
    const LIST_ANY_LESS_THAN = ']<';
    
    const LIST_ANY_LESS_THAN_OR_EQUALS = ']<=';
    
    const LIST_ANY_GREATER_THAN = ']>';
    
    const LIST_ANY_GREATER_THAN_OR_EQUALS = ']>=';

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
            
            foreach (static::getValuesStatic() as $val) {
                $this->labels[$val] = $translator->translate('GLOBAL.COMPARATOR.' . static::findConstant($val));
            }
        }
        
        return $this->labels;
    }
    
    /**
     * 
     * @param string|ComparatorDataType $comparatorOrString
     * @return bool
     */
    public static function isNegative(string|ComparatorDataType $comparatorOrString) : bool
    {
        $cmp = ($comparatorOrString instanceof ComparatorDataType) ? $comparatorOrString->__toString() : $comparatorOrString;
        return $cmp[0] === '!';
    }
    
    /**
     * Returns TRUE if the given comparator can be inverted and FALSE otherwise
     * 
     * @param string|ComparatorDataType $comparatorOrString
     * @return bool
     */
    public static function isInvertable($comparatorOrString) : bool
    {
        if ($comparatorOrString instanceof ComparatorDataType) {
            $cmp = $comparatorOrString->__toString();
        } else {
            $cmp = $comparatorOrString;
        }
        switch ($cmp) {
            case self::BETWEEN: return false;
        }
        return true;
    }
    
    /**
     * 
     * @param string|ComparatorDataType $comparatorOrString
     * @throws RuntimeException
     * @return string|ComparatorDataType
     */
    public static function invert($comparatorOrString)
    {
        if ($comparatorOrString instanceof ComparatorDataType) {
            $asString = false;
            $cmp = $comparatorOrString->__toString();
        } else {
            $asString = true;
            $cmp = $comparatorOrString;
        }
        
        switch ($cmp) {
            case self::EQUALS: $inv = self::EQUALS_NOT; break;
            case self::EQUALS_NOT: $inv = self::EQUALS; break;
            case self::GREATER_THAN: $inv = self::LESS_THAN_OR_EQUALS; break;
            case self::GREATER_THAN_OR_EQUALS: $inv = self::LESS_THAN; break;
            case self::IS: $inv = self::IS_NOT; break;
            case self::IS_NOT: $inv = self::IS; break;
            case self::LESS_THAN: $inv = self::GREATER_THAN_OR_EQUALS; break;
            case self::LESS_THAN_OR_EQUALS: $inv = self::GREATER_THAN; break;
            
            case self::IN: $inv = self::NOT_IN; break;
            case self::NOT_IN: $inv = self::IN; break;
            case self::LIST_INTERSECTS: $inv = self::LIST_NOT_INTERSECTS; break;
            case self::LIST_NOT_INTERSECTS: $inv = self::LIST_INTERSECTS; break;
            case self::LIST_SUBSET: $inv = self::LIST_NOT_SUBSET; break;
            case self::LIST_NOT_SUBSET: $inv = self::LIST_SUBSET; break;
            
            case self::LIST_EACH_EQUALS: $inv = self::LIST_EACH_EQUALS_NOT; break;
            case self::LIST_EACH_EQUALS_NOT: $inv = self::LIST_EACH_EQUALS; break;
            case self::LIST_EACH_GREATER_THAN: $inv = self::LIST_EACH_GREATER_THAN_OR_EQUALS; break;
            case self::LIST_EACH_GREATER_THAN_OR_EQUALS: $inv = self::LIST_EACH_GREATER_THAN; break;
            case self::LIST_EACH_IS: $inv = self::LIST_EACH_IS_NOT; break;
            case self::LIST_EACH_IS_NOT: $inv = self::LIST_EACH_IS; break;
            case self::LIST_EACH_LESS_THAN: $inv = self::LIST_EACH_LESS_THAN_OR_EQUALS; break;
            case self::LIST_EACH_LESS_THAN_OR_EQUALS: $inv = self::LIST_EACH_LESS_THAN; break;
            
            case self::LIST_ANY_EQUALS: $inv = self::LIST_ANY_EQUALS_NOT; break;
            case self::LIST_ANY_EQUALS_NOT: $inv = self::LIST_ANY_EQUALS; break;
            case self::LIST_ANY_GREATER_THAN: $inv = self::LIST_ANY_GREATER_THAN_OR_EQUALS; break;
            case self::LIST_ANY_GREATER_THAN_OR_EQUALS: $inv = self::LIST_ANY_GREATER_THAN; break;
            case self::LIST_ANY_IS: $inv = self::LIST_ANY_IS_NOT; break;
            case self::LIST_ANY_IS_NOT: $inv = self::LIST_ANY_IS; break;
            case self::LIST_ANY_LESS_THAN: $inv = self::LIST_ANY_LESS_THAN_OR_EQUALS; break;
            case self::LIST_ANY_LESS_THAN_OR_EQUALS: $inv = self::LIST_ANY_LESS_THAN; break;
                
            default:
                throw new RuntimeException('Cannot invert comparator "' . $cmp . '"');
        }
        
        return $asString ? $inv : self::fromValue($comparatorOrString->getWorkbench(), $inv);
    }
    
    /**
     * 
     * @param string $comparator
     * @return string|NULL
     */
    public static function convertToListComparator(string $comparator, bool $trueIfAllListItemsMatch = false) : ?string
    {
        if (static::isListComparator($comparator, 'left')) {
            return $comparator;
        }
        switch ($comparator) {
            case self::IN : 
                $result = $trueIfAllListItemsMatch ? self::LIST_SUBSET : self::LIST_INTERSECTS; 
                break;
            case self::NOT_IN: 
                $result = $trueIfAllListItemsMatch ? self::LIST_NOT_SUBSET : self::LIST_NOT_INTERSECTS; 
                break;
            case self::BETWEEN: $result = null; break;
            default:
                $result = ($trueIfAllListItemsMatch ? '[' : ']') . $comparator;
                break;
        }
        return $result;
    }
    
    /**
     * 
     * @param string $comparator
     * @param string $side
     * @return bool
     */
    // TODO geb 2024-12-03: Do we really need this switch case? This is actually covered by isExplicit().
    public static function isListComparator(string $comparator, string $side = null) : bool
    {
        switch ($side) {
            case 'left':
                $left = true;
                $right = false;
                break;
            case 'right':
                $left = false;
                $right = true;
                break;
            case 'both':
            default:
                $left = true;
                $right = true;
                break;
        }
        switch (true) {
            case $comparator === self::LIST_EACH_EQUALS && $left === true:
            case $comparator === self::LIST_EACH_EQUALS_NOT && $left === true:
            case $comparator === self::LIST_EACH_GREATER_THAN && $left === true:
            case $comparator === self::LIST_EACH_GREATER_THAN_OR_EQUALS && $left === true:
            case $comparator === self::LIST_EACH_IS && $left === true:
            case $comparator === self::LIST_EACH_IS_NOT && $left === true:
            case $comparator === self::LIST_EACH_LESS_THAN && $left === true:
            case $comparator === self::LIST_EACH_LESS_THAN_OR_EQUALS && $left === true:
            case $comparator === self::LIST_ANY_EQUALS && $left === true:
            case $comparator === self::LIST_ANY_EQUALS_NOT && $left === true:
            case $comparator === self::LIST_ANY_GREATER_THAN && $left === true:
            case $comparator === self::LIST_ANY_GREATER_THAN_OR_EQUALS && $left === true:
            case $comparator === self::LIST_ANY_IS && $left === true:
            case $comparator === self::LIST_ANY_IS_NOT && $left === true:
            case $comparator === self::LIST_ANY_LESS_THAN && $left === true:
            case $comparator === self::LIST_ANY_LESS_THAN_OR_EQUALS && $left === true:
                $result = true;
                break;
            case $comparator === self::IN && $right === true:
            case $comparator === self::NOT_IN && $right === true:
                $result = true;
                break;
            case $comparator === self::LIST_SUBSET:
            case $comparator === self::LIST_NOT_SUBSET:
            case $comparator === self::LIST_INTERSECTS:
            case $comparator === self::LIST_NOT_INTERSECTS:
                $result = true;
                break;
            default:
                $result = false;
                break;
        }
        return $result;
    }

    /**
     * Check if a comparator is atomic.
     * 
     * A comparator is atomic if it cannot be separated into a concatenation of SCALAR comparators.
     * In other words all LIST comparators are not atomic, because they can be separated into a set
     * of SCALAR comparators concatenated by a logical operator. 
     * 
     * @param string $comparator
     * @return bool
     */
    public static function isAtomic(string $comparator) : bool
    {
        switch ($comparator) {
            case self::EQUALS:
            case self::EQUALS_NOT:
            case self::GREATER_THAN:
            case self::GREATER_THAN_OR_EQUALS:
            case self::IS:
            case self::IS_NOT:
            case self::LESS_THAN:
            case self::LESS_THAN_OR_EQUALS:
                return true;
        }
        return false;
    }

    /**
     * Divide a condition into sub-conditions that are guaranteed to be atomic.
     *
     * @param ConditionInterface $condition
     * @param bool               $trimValues
     * @return ConditionalExpressionInterface
     * @see ComparatorDataType::isExplicit()
     *
     */
    public static function atomizeCondition(ConditionInterface $condition, bool $trimValues = true) : ConditionalExpressionInterface
    {
        // IDEA also support ConditionGroups atomizing their inner conditions and nested groups recursively
        $rightSideValues = $condition->getValue();
        if(!is_array($rightSideValues)) {
            $expression = $condition->getExpression();
            $delimiter = $expression->isMetaAttribute() ? $expression->getAttribute()->getValueListDelimiter() : ',';
            $rightSideValues = explode($delimiter, $rightSideValues);
        }

        $comparator = $condition->getComparator();
        
        if(self::isAtomic($comparator)) {
            return $condition;
        }

        if($isNegative = self::isNegative($comparator)){
            $comparator = substr($comparator, 1);
        }
        
        $scalarComparator = str_replace(['[',']'], '', $comparator);
        /*$setComparator = self::substringBefore($comparator, $scalarComparator);
        
        $operator = match ($setComparator) {
            '[', '[[', '][' => EXF_LOGICAL_OR,
            default => EXF_LOGICAL_AND,
        };*/
        
        if(empty($scalarComparator)) {
            $scalarComparator = '==';
        }
        
        if($isNegative) {
            $scalarOperator = EXF_LOGICAL_AND;
            $scalarComparator = '!' . $scalarComparator;
        } else {
            $scalarOperator = EXF_LOGICAL_OR;
        }

        $workbench = $condition->getWorkbench();
        $ignoreEmpty = $condition->willIgnoreEmptyValues();
        $expression = $condition->getExpression();
        $conditionGroup = ConditionGroupFactory::createEmpty($workbench, $scalarOperator, null, $ignoreEmpty);
        foreach ($rightSideValues as $value) {
            if($trimValues) {
                $value = trim($value);
            }
            
            $condition = new Condition(
                $workbench,
                $expression,
                $scalarComparator,
                $value,
                $ignoreEmpty
            );
            $conditionGroup->addCondition($condition);
        }
        
        return $conditionGroup;
    }
}