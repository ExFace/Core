<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;

class ContextInvalidKeyError extends OutOfBoundsException implements ContextExceptionInterface, ErrorExceptionInterface {
	
	use ContextExceptionTrait;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
	 */
	public function __construct (ContextInterface $context, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_context($context);
	}
	
}