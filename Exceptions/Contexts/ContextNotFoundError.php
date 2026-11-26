<?php
namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;
use exface\Core\Exceptions\OutOfBoundsException;

class ContextNotFoundError extends OutOfBoundsException implements ErrorExceptionInterface, NotFoundExceptionInterface
{

    public function getDefaultAlias()
    {
        return '6T5E24E';
    }
}