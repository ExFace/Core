<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Factories\DataSourceFactory;

class DataManager implements DataManagerInterface
{
    private $active_sources = [];

    private $cache;

    private $exface;

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
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
        if ($this->active_sources[$id . '-' . $data_connection_id_or_alias]) {
            return $this->active_sources[$id . '-' . $data_connection_id_or_alias];
        }
        
        // if it is a new source, create it here
        $data_source = DataSourceFactory::createFromModel($this->getWorkbench(), $id, $data_connection_id_or_alias);
        $this->active_sources[$id . '-' . $data_connection_id_or_alias] = $data_source;
        return $data_source;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::disconnectAll()
     */
    public function disconnectAll()
    {
        foreach ($this->active_sources as $src) {
            $src->getConnection()->disconnect();
        }
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
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    function getWorkbench()
    {
        return $this->exface;
    }
}
?>