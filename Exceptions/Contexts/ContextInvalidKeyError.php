<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;

class ContextInvalidKeyError extends OutOfBoundsException implements ContextExceptionInterface, ErrorExceptionInterface {
	
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