<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;

abstract class AbstractSqlInstallerPlugin extends Formula
{
    private ?SqlDataConnectorInterface $connector = null;

    public function setConnector(?SqlDataConnectorInterface $connector) : AbstractSqlInstallerPlugin
    {
        $this->connector = $connector;
        return $this;
    }

    public function getConnector() : ?SqlDataConnectorInterface
    {
        return $this->connector;
    }

    public function hasConnector() : bool
    {
        return $this->connector !== null;
    }
}