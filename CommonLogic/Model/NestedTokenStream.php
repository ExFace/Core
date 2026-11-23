<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use function AdminNeo\number;

/**
 * Empty token stream class for formula token streams.
 * An EmptyTokenStream has no expression, attributes or nested formulas.
 * 
 * @author ralf.mulansky
 *
 */
class NestedTokenStream implements FormulaTokenStreamInterface
{
    private int $index;
    private string $formulaName;
    private ?SymfonyTokenStream $parentStream;
    private ?array $argumentsNormalized = null;

    /**
     *
     * @param int                              $index
     * @param string                           $formulaName
     * @param FormulaTokenStreamInterface|null $parentStream
     */
    public function __construct(int $index, string $formulaName, ?FormulaTokenStreamInterface $parentStream)
    {
        $this->index = $index;
        $this->formulaName = $formulaName;
        $this->parentStream = $parentStream;
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
        if(!$this->parentStream instanceof SymfonyTokenStream) {
            return [];
        }
        
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArguments()
     */
    public function getArguments(): array
    {
        if(!$this->parentStream instanceof SymfonyTokenStream) {
            return [];
        }
        
        return $this->getArgumentsNormalized();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function getArgument(int $index) : ?string
    {
        if(!$this->parentStream instanceof SymfonyTokenStream) {
            return null;
        }
        
        $index = $this->normalizeIndex($index);
        return $index !== null ? $this->getArgumentsNormalized()[$index] : null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function isArgumentAttribute(int $index) : bool
    {
        if(!$this->parentStream instanceof SymfonyTokenStream) {
            return false;
        }
        
        return $this->parentStream->isArgumentAttribute($index);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::__toString()
     */
    public function __toString() : string
    {
        if(!$this->parentStream instanceof SymfonyTokenStream) {
            return '';
        }
        
        return $this->parentStream->__toString();
    }
    
    
}