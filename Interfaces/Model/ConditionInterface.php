<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * A condition is a simple conditional predicate consisting of a (left) expression,
 * a comparator (e.g. =, <, etc.) and a (right) value expression: e.g. "expr = a" or 
 * "date > 01.01.1970", etc.
 * 
 * Conditions can be combined to condition groups (see CondtionGroupInterface) using 
 * logical operators like AND, OR, etc.
 * 
 * Conditions are immutable!
 * 
 * TODO make the value an expression too, not just a scalar.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ConditionInterface extends ConditionalExpressionInterface
{
    /**
     * Returns expression for the left side
     *
     * @return ExpressionInterface
     */
    public function getLeftExpression() : ExpressionInterface;
    
    /**
     * Returns expression for the right side
     *
     * @return ExpressionInterface
     */
    public function getRightExpression() : ExpressionInterface;
    
    /**
     * Returns the comparison operator from this condition.
     * Normally it is one of the EXF_COMPARATOR_xxx constants.
     *
     * @return string
     */
    public function getComparator() : string;
    
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
}

