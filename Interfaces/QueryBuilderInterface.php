<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;

/**
 * Query builders build data source specific queries for the base operations
 * create, read, update, delete and count and perform them on the respective
 * connections.
 * 
 * For the outside world query builders are factories for instances of
 * DataQueryResultDataInterface, that contain the results of a query
 * on any data source in a universal format.
 * 
 * @author Andrej Kabachnik
 *
 */
interface QueryBuilderInterface extends WorkbenchDependantInterface
{  
    /**
     * 
     * @return QueryBuilderSelectorInterface
     */
    public function getSelector() : QueryBuilderSelectorInterface;
    
    /**
     * Performs a create query.
     * Returns the number of successfully created rows.
     *
     * @param string $data_connection            
     * @return DataQueryResultDataInterface
     */
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface;

    /**
     * Performs a read query.
     * Returns the number of read rows.
     *
     * @param string $data_connection            
     * @return DataQueryResultDataInterface
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface;

    /**
     * Performs an update query.
     * Returns the number of successfully updated rows.
     *
     * @param string $data_connection            
     * @return DataQueryResultDataInterface
     */
    public function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface;

    /**
     * Performs a delete query.
     * Returns the number of deleted rows.
     *
     * @param string $data_connection            
     * @return DataQueryResultDataInterface
     */
    public function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface;
    
    /**
     * 
     * @param DataConnectionInterface $data_connection
     * @return DataQueryResultDataInterface
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface;
    
    // TODO
}