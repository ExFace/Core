<?php
namespace exface\Core\Exceptions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;

/**
 * Exception thrown a UXON property cannot be mapped to the corresponding property or setter method of the importing entity
 * (widget, action, etc.).
 *
 * If the entity exists alread, it's class-specific exceptions (e.g. widget or action exceptions) should be preferred
 * to this general exception.
 *
 * @author Andrej Kabachnik
 *        
 */
class UxonMapError extends UxonParserError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias(){
        return '74JU2AU';
    }
}
?>