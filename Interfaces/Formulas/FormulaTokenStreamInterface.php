<?php

namespace exface\Core\Interfaces\Formulas;

interface FormulaTokenStreamInterface
{
    public function __construct(string $expression);
    
    /**
     *
     * @return string|NULL
     */
    public function getFormulaName() : ?string;
    
    /**
     *
     * @return array
     */
    public function getNestedFormulas() : array;
    
    /**
     *
     * @return array
     */
    public function getArguments() : array;
    
    /**
     *
     * @return string
     */
    public function getExpression() : string;
    
}