<?php

namespace exface\Core\Interfaces\Formulas;

interface FormulaExpressionLanguageInterface
{
    /**
     * Evaluates the in the formula given expression using the data given in `row`
     * 
     * @param FormulaInterface $formula
     * @param array $row
     */
    public function evaluate(FormulaInterface $formula, array $row);
}