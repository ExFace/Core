<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

interface DataQueryEventInterface extends EventInterface
{
    /**
     * Returns the data query, for which the event was triggered.
     * 
     * @return DataQueryInterface
     */
    public function getQuery() : DataQueryInterface;
}