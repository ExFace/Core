<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;

Interface DataTypeExceptionInterface
{
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
}