<?php
namespace exface\Core\Interfaces\DataSources;

/**
 * Interface for data connectors, that support custom queries - e.g. SQL.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TextualQueryConnectorInterface extends DataConnectionInterface
{
    /**
     * Runs any query returning a data query instance
     *
     * @param string $string            
     * @return DataQueryInterface
     */
    public function runCustomQuery(string $string) : DataQueryInterface;
}