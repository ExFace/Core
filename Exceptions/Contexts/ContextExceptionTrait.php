<?php

namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output context specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait ContextExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createParentWidget;
	}

    private $context = null;

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

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::getContext()
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::setContext()
     */
    public function setContext(ContextInterface $value)
    {
        $this->context = $value;
        return $this;
    }
}
?>