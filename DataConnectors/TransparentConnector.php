<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * This simple data connector merely returns the given query right back while being fully compilant to all data connector specs.
 *
 * It does not have any configuration and actually does nothing. This connector is usefull for data sources, where the query or
 * the query builder can do everything themselves without needing a connection manager, credentials, or anything else.
 *
 * @author Andrej Kabachnik
 *        
 */
class TransparentConnector extends AbstractDataConnectorWithoutTransactions
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * @return \SplFileInfo[]
     */
    protected function performQuery(DataQueryInterface $query)
    {
        return $query;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        return;
    }
}
?>