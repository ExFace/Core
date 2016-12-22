<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSources\ContextExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Exceptions\OutOfBoundsException;

class ContextInvalidKeyError extends OutOfBoundsException implements ContextExceptionInterface, ErrorExceptionInterface {
	
	use ContextExceptionTrait;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
	 */
	public function __construct (ContextInterface $context, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_context($context);
	}
	
}