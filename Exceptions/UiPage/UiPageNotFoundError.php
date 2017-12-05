<?php
namespace exface\Core\Exceptions\UiPage;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\NotFoundError;

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