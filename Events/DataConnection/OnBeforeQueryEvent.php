<?php
namespace exface\Core\Events\DataConnection;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Events\DataQueryEventInterface;

/**
 * Event fired before a query has been performed on a data connection.
 *
 * @event exface.Core.DataConnection.OnBeforeQuery
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeQueryEvent extends AbstractDataConnectionEvent implements DataQueryEventInterface
{
    private $query = null;
    
    /**
     * 
     * @param DataConnectionInterface $connection
     * @param DataQueryInterface $query
     */
    public function __construct(DataConnectionInterface $connection, DataQueryInterface $query)
    {
        parent::__construct($connection);
        $this->query = $query;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnBeforeQuery';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataQueryEventInterface::getQuery()
     */
    public function getQuery() : DataQueryInterface
    {
        return $this->query;
    }
}