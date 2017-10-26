<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a UI page was not found or an invalid page id was requested.
 *
 * @author Andrej Kabachnik
 *        
 */
class UiPageNotFoundError extends NotFoundError implements ErrorExceptionInterface
{
    public function getDefaultAlias()
    {
        return '6WNC22Z';
    }
}
?>