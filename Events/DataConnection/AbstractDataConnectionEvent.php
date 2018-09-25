<?php
namespace exface\Core\Events\DataConnection;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Events\DataConnectionEventInterface;

/**
 * 
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractDataConnectionEvent extends AbstractEvent implements DataConnectionEventInterface
{
    private $connection = null;
    
    /**
     * 
     * @param DataConnectionInterface $connection
     */
    public function __construct(DataConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataConnectionEventInterface::getConnection()
     */
    public function getConnection() : DataConnectionInterface
    {
        return $this->connection;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->connection->getWorkbench();
    }
}