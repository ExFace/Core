<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;

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
    private SymfonyTokenStream $parentStream;
    
    private ?array $arguments = null;

    /**
     *
     * @param int                         $index
     * @param string                      $formulaName
     * @param FormulaTokenStreamInterface $parentStream
     */
    public function __construct(int $index, string $formulaName, FormulaTokenStreamInterface $parentStream)
    {
        if(!$parentStream instanceof SymfonyTokenStream) {
            throw new InvalidArgumentException('Invalid argument: Received "' . $parentStream::class . '", expected "SymfonyTokenStream".');
        }
        
        $this->index = $index;
        $this->formulaName = $formulaName;
        $this->parentStream = $parentStream;
    }

    /**
     * @inheritDoc
     */
    public function getFormulaName() : ?string
    {
        return $this->formulaName;
    }

    /**
     * @inheritDoc
     */
    public function getNestedFormulas() : array
    {
        return $this->parentStream->getNestedFormulas();
    }

    /**
     * @inheritDoc
     */
    public function getAttributes() : array
    {
        return $this->parentStream->getAttributes();
    }

    /**
     * @inheritDoc
     */
    public function getArgumentsAll(): array
    {
        return $this->parentStream->getArgumentsAll();
    }

    /**
     * @inheritDoc
     */
    public function getArguments(): array
    {
        if($this->arguments === null) {
            $this->arguments = $this->parentStream->getArguments($this->index);
        }
        
        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    public function getArgument(int $index) : ?SymfonyArgument
    {
        return $this->getArguments()[$index] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isArgumentAttribute(int $index) : bool
    {
        return $this->getArguments()[$index]?->isAttribute() ?? false;
    }

    /**
     * @inheritDoc
     */
    public function __toString() : string
    {
        return $this->parentStream->__toString();
    }
}