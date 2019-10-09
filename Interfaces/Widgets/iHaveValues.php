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
     */
    public function setValues($expressionOrArrayOrDelimitedString) : iHaveValues;

    /**
     *
     * @param array $values            
     */
    public function setValuesFromArray(array $values) : iHaveValues;
}