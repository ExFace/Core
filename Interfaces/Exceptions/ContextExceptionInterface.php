<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Contexts\ContextInterface;

Interface ContextExceptionInterface
{
    /**
     *
     * @return ContextInterface
     */
    public function getContext();
}