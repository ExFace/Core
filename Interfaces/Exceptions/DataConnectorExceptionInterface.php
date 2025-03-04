<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface DataConnectorExceptionInterface
{
    /**
     *
     * @return DataConnectionInterface
     */
    public function getConnector() : DataConnectionInterface;
}
