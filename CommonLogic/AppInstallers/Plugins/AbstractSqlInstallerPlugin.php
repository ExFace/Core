<?php

namespace exface\Core\CommonLogic\AppInstallers\Plugins;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;

/**
 * Base class for SQL installer plugins with some additional properties for ease of use.
 */
abstract class AbstractSqlInstallerPlugin extends Formula
{
    private ?SqlDataConnectorInterface $connector = null;

    /**
     * @param SqlDataConnectorInterface|null $connector
     * @return $this
     */
    public function setConnector(?SqlDataConnectorInterface $connector) : AbstractSqlInstallerPlugin
    {
        $this->connector = $connector;
        return $this;
    }

    /**
     * Get the data connector for this instance, if any.
     * 
     * @return SqlDataConnectorInterface|null
     */
    public function getConnector() : ?SqlDataConnectorInterface
    {
        return $this->connector;
    }

    /**
     * Returns TRUE, if this instance has a data connector assigned to it.
     * 
     * @return bool
     */
    public function hasConnector() : bool
    {
        return $this->connector !== null;
    }
}