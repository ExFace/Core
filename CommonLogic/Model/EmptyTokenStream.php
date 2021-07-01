<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;

class EmptyTokenStream implements FormulaTokenStreamInterface
{
    private $formulaName = null;
    
    public function __construct(string $formulaName)
    {
        $this->formulaName = $formulaName;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFormulaName() : ?string
    {
        return $this->formulaName;
    }
    
    /**
     * 
     * @return array
     */
    public function getNestedFormulas() : array
    {
        return [];
    }
    
    /**
     * 
     * @return array
     */
    public function getArguments() : array
    {
        return [];
    }
    
    /**
     * 
     * @return string
     */
    public function getExpression() : string
    {
        return '';
    }
}