<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

class MetaRelationNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface {
	
	use MetaObjectExceptionTrait;
	
}
?>