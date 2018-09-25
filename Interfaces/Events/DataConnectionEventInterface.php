<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface DataConnectionEventInterface extends EventInterface
{
    /**
     * Returns the data connection, for which the event was triggered.
     * 
     * @return DataConnectionInterface
     */
    public function getConnection() : DataConnectionInterface;
}