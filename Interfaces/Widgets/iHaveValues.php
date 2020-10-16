<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\ExpressionInterface;

interface iHaveValues extends iHaveValue
{

    /**
     *
     * @return array
     */
    public function getValues() : array;

    /**
     *
     * @param ExpressionInterface|array|string $expression_or_delimited_list    
     * @param bool $parseStringAsExpression
     * 
     * @return iHaveValues        
     */
    public function setValues($expressionOrArrayOrDelimitedString, bool $parseStringAsExpression = true) : iHaveValues;

    /**
     * 
     * @param array $values
     * @param bool $parseStringsAsExpressions
     * 
     * @return iHaveValues
     */
    public function setValuesFromArray(array $values, bool $parseStringsAsExpressions = true) : iHaveValues;
}