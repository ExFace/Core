<?php

namespace exface\Core\Interfaces\Formulas;

interface FormulaTokenStreamInterface
{    
    /**
     * Returns the base formula name or NULL if no name can be found.
     *
     * @return string|NULL
     */
    public function getFormulaName() : ?string;
    
    /**
     * Returns the nested formula names as array.
     * 
     * @return string[]
     */
    public function getNestedFormulas() : array;
    
    /**
     * Returns the attribute aliases (with aggregators if given).
     *
     * @return string[]
     */
    public function getAttributes() : array;
    
    /**
     * Returns a flat array of all scalar arguments (strings, numbers, booleans) 
     * including those from nested formulas
     * 
     * @return string[]
     */
    public function getArguments() : array;
    
    /**
     * Returns the expression.
     *
     * @return string
     */
    public function __toString() : string;
}