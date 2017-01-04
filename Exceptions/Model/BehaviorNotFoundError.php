<?php
namespace exface\Core\Exceptions\Model;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

/**
 * Exception thrown if an object's behavior could not be loaded (i.e. class not found)
 * 
 * @author Andrej Kabachnik
 *
 */
class BehaviorNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface {
	
	use MetaObjectExceptionTrait;
	
}
?>