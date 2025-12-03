<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Facades\FacadeInterface;

Interface FacadeExceptionInterface
{
    /**
     *
     * @return FacadeInterface
     */
    public function getFacade() : facadeInterface;
}