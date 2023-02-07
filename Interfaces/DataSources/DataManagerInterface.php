<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface DataManagerInterface extends WorkbenchDependantInterface
{

    /**
     * Returns a data source object used as interface for any data sources in exface.
     * The data source can either
     * be loaded automatically from the user configured data sources (located in the default data source) by
     * passing merely the $id or created manually by specifying the connector, config array and query builder.
     * In any case the active data source is cached, so it only needs to be instantiated once. The caching is
     * also the reason for placing this method in the data class, which actually holds the cache.
     *
     * IDEA it would be really cool to use an exface data source to get the data source config too. So that
     * the data source information can also be stored in any kind of data source. Currently it would only
     * work with SQL DBs because of the hard coded syntax. To use a data source here as well, we would probably
     * need to access it via meta object. This would cause trouble with the name-value pairs in the config,
     * since they are not real attributes. Should think about that later.
     *
     * @param string $uid
     * @param string|NULL $data_connection_id_or_alias
     * 
     * @return DataSourceInterface
     */
    public function getDataSource($uid, $data_connection_id_or_alias = NULL);

    /**
     * Shut down all open connections
     */
    public function disconnectAll();

    /**
     * Starts a new global transaction and returns it
     *
     * @return DataTransactionInterface
     */
    public function startTransaction() : DataTransactionInterface;
    
    /**
     * 
     * @return array
     */
    public function getTransactions() : array;
}
?>