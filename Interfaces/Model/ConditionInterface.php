<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * A condition is a simple conditional predicate to compare two expressions.
 * 
 * Each condition (e.g. `expression = value` or `date > 01.01.1970`) consists of 
 * - a (left) expression,
 * - a comparator (e.g. =, <, etc.) and 
 * - a (right) value expression
 * 
 * Depending on the comparator, the value may be a scalar or an array (for IN-comparators).
 * 
 * @see ConditionGroupInterface
 *
 * @author Andrej Kabachnik
 *        
 */
interface ConditionInterface extends ConditionalExpressionInterface
{
    /**
     * Returns the expression to filter
     *
     * @return ExpressionInterface
     */
    public function getExpression() : ExpressionInterface;
    
    /**
     * 
     * @return ExpressionInterface
     */
    public function getLeftExpression() : ExpressionInterface;
    
    /**
     * Returns the value to compare to
     *
     * @return mixed
     */
    public function getValue() : ?string;
    
    /**
     * 
     * @return ExpressionInterface
     */
    public function getRightExpression() : ExpressionInterface;
    
    /**
     * Changes right side of the condition.
     *
     * @param string|NULL $value
     * @return ConditionInterface
     */
    public function setValue(?string $value) : ConditionInterface;
    
    /**
     * Removes the right side of the condition (as if it was never set).
     * 
     * @return ConditionInterface
     */
    public function unsetValue() : ConditionInterface;
    
    /**
     * Returns the comparison operator from this condition.
     * Normally it is one of the EXF_COMPARATOR_xxx constants.
     *
     * @return string
     */
    public function getComparator() : string;
    
    /**
     * TODO move to ComparatorDataType once it is here
     * 
     * 
     * @param string $value
     * @throws UnexpectedValueException
     * @return string|boolean
     */
    public static function sanitizeComparator(string $value) : string;
    
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
    
    /**
     * Returns the attribute_alias to filter if the filter is based upon an attribute or FALSE otherwise
     *
     * @return string|boolean
     */
    public function getAttributeAlias();
    
    /**
     * 
     * @return bool
     */
    public function willIgnoreEmptyValues() : bool;
}

