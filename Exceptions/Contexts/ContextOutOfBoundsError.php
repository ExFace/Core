<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextExceptionTrait;
use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;

/**
 * Exception thrown requested context data cannot be found. In contrast to the regular OutOfBoundsException, it's
 * context-specific version will output more usefull debug information like the context scope, data etc.
 * 
 * Typical use-cases are trying to fetch an action from history, which is not there, or requesting a favorited instance,
 * that cannot be found in the favorites. 
 *
 * @author Andrej Kabachnik
 *
 */
class ContextOutOfBoundsError extends OutOfBoundsException implements ContextExceptionInterface, ErrorExceptionInterface {
	
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