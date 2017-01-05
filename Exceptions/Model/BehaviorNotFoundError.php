<?php
namespace exface\Core\Exceptions\Model;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;
use exface\Core\CommonLogic\Model\Object;

/**
 * Exception thrown if an object's behavior could not be loaded (i.e. class not found)
 * 
 * @author Andrej Kabachnik
 *
 */
class BehaviorNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface {
	
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