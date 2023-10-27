<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;

/**
 * Empty token stream class for formula token streams.
 * An EmptyTokenStream has no expression, attributes or nested formulas.
 * 
 * @author ralf.mulansky
 *
 */
class EmptyTokenStream implements FormulaTokenStreamInterface
{
    private $formulaName = null;
    
    /**
     * 
     * @param string $formulaName
     */
    public function __construct(string $formulaName)
    {
        $this->formulaName = $formulaName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getFormulaName()
     */
    public function getFormulaName() : ?string
    {
        return $this->formulaName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getNestedFormulas()
     */
    public function getNestedFormulas() : array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getAttributes()
     */
    public function getAttributes() : array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArguments()
     */
    public function getArguments(): array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::__toString()
     */
    public function __toString() : string
    {
        return '';
    }
}