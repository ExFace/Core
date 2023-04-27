<?php
namespace exface\Core\Interfaces\Exceptions;

use Psr\Http\Message\ServerRequestInterface;

Interface HttpServerRequestExceptionInterface extends ErrorExceptionInterface
{
    public function getRequest() : ServerRequestInterface;
}