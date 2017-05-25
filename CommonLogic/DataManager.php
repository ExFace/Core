<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\DataConnectorFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Factories\DataSourceFactory;

class DataManager implements DataManagerInterface
{

    private $active_connections;

    private $active_sources;

    private $cache;

    private $exface;

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
        $this->active_sources = array();
        $this->active_connections = array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::getDataSource()
     */
    function getDataSource($id, $data_connection_id_or_alias = NULL)
    {
        // first check the cache
        if ($this->active_sources[$id . '-' . $data_connection_id_or_alias])
            return $this->active_sources[$id . '-' . $data_connection_id_or_alias];
        
        // if it is a new source, create it here
        $model = $this->getWorkbench()->model();
        $data_source = DataSourceFactory::createForDataConnection($model, $id, $data_connection_id_or_alias);
        $this->active_sources[$id . '-' . $data_connection_id_or_alias] = $data_source;
        return $data_source;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::connect()
     */
    public function connect($data_connector, $config, $data_connection_id)
    {
        // check if connection exists (we only need a data_connection once!)
        if ($data_connection_id && $this->active_connections[$data_connection_id]) {
            return $this->active_connections[$data_connection_id];
        }
        
        $con = DataConnectorFactory::createFromAlias($this->exface, $data_connector, $config);
        $con->connect();
        
        // cache the new connection
        $this->active_connections[$data_connection_id] = $con;
        return $con;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::disconnectAll()
     */
    function disconnectAll()
    {
        foreach ($this->active_connections as $src) {
            $src->disconnect();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::getDataConnection()
     */
    function getDataConnection($data_source_id, $data_connection_id_or_alias = NULL)
    {
        $data_source = $this->getDataSource($data_source_id, $data_connection_id_or_alias);
        return $this->connect($data_source->getDataConnectorAlias(), $data_source->getConnectionConfig(), $data_source->getConnectionId());
    }

    /**
     *
     * @deprecated use QueryBuilderFactory instead!
     *             Returns the default query builder for the given data source
     * @param unknown $data_source_id            
     */
    function getQueryBuilder($data_source_id)
    {
        $data_source = $this->getDataSource($data_source_id);
        return $data_source->getQueryBuilderAlias();
    }

    /**
     *
     * @deprecated use DataSheetFactory instead
     * @param \exface\Core\CommonLogic\Model\Object $meta_object            
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function createDataSheet(\exface\Core\CommonLogic\Model\Object $meta_object)
    {
        return DataSheetFactory::createFromObject($meta_object);
    }

    /**
     *
     * @deprecated use DataContext instead
     * @param unknown $path            
     * @param unknown $id            
     * @param unknown $value            
     */
    function setCache($path, $id, $value)
    {
        $this->cache[$path][$id] = $value;
    }

    /**
     *
     * @deprecated use DataContext instead
     * @param unknown $path            
     * @param unknown $id            
     * @return unknown
     */
    function getCache($path, $id)
    {
        return $this->cache[$path][$id];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::startTransaction()
     */
    public function startTransaction()
    {
        $transaction = new DataTransaction($this);
        $transaction->start();
        return $transaction;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    function getWorkbench()
    {
        return $this->exface;
    }
}
?>