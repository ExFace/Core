<?php

namespace exface\Core\Interfaces\Formulas;

use exface\Core\CommonLogic\Model\SymfonyArgument;

interface FormulaTokenStreamInterface
{    
    /**
     * Returns the base formula name or NULL if no name can be found.
     *
     * @return string|NULL
     */
    public function getFormulaName() : ?string;
    
    /**
     * Returns the names of ALL formulas, except for the root formula, as a flat array.
     * 
     * @return string[]
     */
    public function getNestedFormulas() : array;
    
    /**
     * Returns ALL attribute aliases (with aggregators if given) for this token stream.
     *
     * @return string[]
     */
    public function getAttributes() : array;
    
    /**
     * Returns a flat array of all arguments for THIS formula, i.e. strings, numbers, booleans 
     * and nested formulas.
     * 
     * @return SymfonyArgument[]
     */
    public function getArguments() : array;

    /**
     * Returns a flat array of all scalar arguments (strings, numbers, booleans)
     * including formula calls and those from nested formulas.
     *
     * @return SymfonyArgument[]
     */
    public function getArgumentsAll() : array;

    /**
     * Returns the argument at the given position in the list of arguments for THIS formula (starting with 0).
     * 
     * NOTE: The index is relative to the formula that this token stream belongs to. 
     * For `=Concatenate(Round(Attribute, 2), ' Value')` it would be:
     * - `Concatenate`: [0 => Round, 1 => ' Value']
     * - `Round`: [0 => Attribute, 1 => 2] 
     * 
     * @param int $index
     * @return SymfonyArgument|null
     */
    public function getArgument(int $index) : ?SymfonyArgument;

    /**
     * Returns TRUE if the argument at the given position is an attribute alias.
     * 
     * @param int $index
     * @return bool
     */
    public function isArgumentAttribute(int $index) : bool;
    
    /**
     * Returns the expression.
     *
     * @return string
     */
    public function __toString() : string;
}