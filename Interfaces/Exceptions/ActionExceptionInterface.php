<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Actions\ActionInterface;

Interface ActionExceptionInterface
{
    /**
     *
     * @return ActionInterface
     */
    public function getAction();
}