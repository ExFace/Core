<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\CommonLogic\UxonObject;

Interface UxonExceptionInterface
{
    /**
     *
     * @return UxonObject
     */
    public function getUxon();

    /**
     * @return array
     */
    public function getPath() : array;
}