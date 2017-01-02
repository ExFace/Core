<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;

class ContextRuntimeError extends RuntimeException implements ContextExceptionInterface, ErrorExceptionInterface {
	
	use ContextExceptionTrait;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
	 */
	public function __construct (ContextInterface $context, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_context($context);
	}
	
}