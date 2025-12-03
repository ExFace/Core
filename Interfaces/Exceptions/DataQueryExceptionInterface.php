<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

interface DataQueryExceptionInterface
{
    /**
     *
     * @return DataQueryInterface
     */
    public function getQuery(): DataQueryInterface;
}