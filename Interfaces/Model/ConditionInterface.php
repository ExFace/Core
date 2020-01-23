<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

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
     * Returns the expression to filter
     *
     * @return ExpressionInterface
     */
    public function getExpression() : ExpressionInterface;
    
    /**
     * Returns the value to compare to
     *
     * @return mixed
     */
    public function getValue() : ?string;
    
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
     * @param DataSheetInterface $data_sheet
     * @param int $row_number
     * @return bool
     */
    public function evaluate(DataSheetInterface $data_sheet = null, int $row_number = null) : bool;
}

