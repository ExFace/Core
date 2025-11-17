<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Model\MetaObjectInterface;

Interface MetaObjectExceptionInterface extends ExceptionInterface
{
    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface;
}