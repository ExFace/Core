<?php namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaObjectExceptionTrait;
use exface\Core\CommonLogic\Model\Object;

/**
 * Exception thrown if a configuration option for a meta object behavior is invalid or missing. 
 * 
 * Behaviors are encouraged to produce this error if the user creates an invalid UXON configuration for the behavior
 * invalid option values are set programmatically.
 *
 * @author Andrej Kabachnik
 *
 */
class BehaviorConfigurationError extends UnexpectedValueException {
	
	use MetaObjectExceptionTrait;
	
	/**
	 *
	 * @param Object $meta_object
	 * @param string $message
	 * @param string $alias
	 * @param \Throwable $previous
	 */
	public function __construct (Object $meta_object, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_meta_object($meta_object);
	}
}
?>