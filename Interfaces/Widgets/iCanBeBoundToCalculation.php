<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * Interface for model entities (e.g. Widgets), that can be bound to a calculation
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeBoundToCalculation
{


    /**
     *
     * @param string $expression
     * @return iCanBeBoundToCalculation
     */
    public function setCalculation(string $expression) : iCanBeBoundToCalculation;

    /**
     *
     * @return bool
     */
    public function isCalculated() : bool;

    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getCalculationExpression() : ?ExpressionInterface;
}