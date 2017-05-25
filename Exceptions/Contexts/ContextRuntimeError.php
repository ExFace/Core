<?php

namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;

/**
 * Exception should be used in contexts instead of a regular RuntimeException to enrich the debug output with
 * context specific information like the context scope and current data.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextRuntimeError extends RuntimeException implements ContextExceptionInterface, ErrorExceptionInterface
{
    
    use ContextExceptionTrait;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
     */
    public function __construct(ContextInterface $context, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setContext($context);
    }
}