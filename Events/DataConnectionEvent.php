<?php
namespace exface\Core\Events;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * Data connection event names consist of the alias of the connector followed 
 * by "DataConnection" and the respective event type: e.g.
 * exface.sqlDataConnector.DataConnectors.MySqlConnector.DataConnection.Query.Before, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataConnectionEvent extends ExfaceEvent
{

    private $data_connection = null;

    private $current_query = null;

    /**
     *
     * @return DataConnectionInterface
     */
    public function getDataConnection()
    {
        return $this->data_connection;
    }

    /**
     *
     * @param DataConnectionInterface $connection            
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        $this->data_connection = $connection;
        return $this;
    }

    /**
     *
     * @return DataQueryInterface
     */
    public function getCurrentQuery()
    {
        return $this->current_query;
    }

    /**
     *
     * @param DataQueryInterface $value            
     */
    public function setCurrentQuery(DataQueryInterface $value)
    {
        $this->current_query = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Events\ExfaceEvent::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getDataConnection()->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'DataConnection';
    }
}