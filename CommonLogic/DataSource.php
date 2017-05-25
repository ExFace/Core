<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\CommonLogic\Model\Model;

class DataSource implements DataSourceInterface
{

    private $model;

    private $data_connector;

    private $connection_id;

    private $query_builder;

    private $data_source_id;

    private $connection_config = array();

    private $read_only = false;

    function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getModel()
     */
    public function getModel()
    {
        return $this->model;
    }

    public function getWorkbench()
    {
        return $this->getModel()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getConnection()
     */
    public function getConnection()
    {
        return $this->getWorkbench()->data()->getDataConnection($this->getId(), $this->getConnectionId());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getId()
     */
    public function getId()
    {
        return $this->data_source_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setId()
     */
    public function setId($value)
    {
        $this->data_source_id = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getDataConnectorAlias()
     */
    public function getDataConnectorAlias()
    {
        return $this->data_connector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setDataConnectorAlias()
     */
    public function setDataConnectorAlias($value)
    {
        $this->data_connector = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getConnectionId()
     */
    public function getConnectionId()
    {
        return $this->connection_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setConnectionId()
     */
    public function setConnectionId($value)
    {
        $this->connection_id = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getQueryBuilderAlias()
     */
    public function getQueryBuilderAlias()
    {
        return $this->query_builder;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setQueryBuilderAlias()
     */
    public function setQueryBuilderAlias($value)
    {
        $this->query_builder = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::getConnectionConfig()
     */
    public function getConnectionConfig()
    {
        return is_array($this->connection_config) ? $this->connection_config : array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setConnectionConfig()
     */
    public function setConnectionConfig($value)
    {
        $this->connection_config = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::isReadOnly()
     */
    public function isReadOnly()
    {
        return $this->read_only;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::setReadOnly()
     */
    public function setReadOnly($value)
    {
        $this->read_only = $value;
        return $this;
    }
}
?>