<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;

abstract class AbstractDataConnectorWithoutTransactions extends AbstractDataConnector
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionStart()
     */
    public function transactionStart()
    {
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionCommit()
     */
    public function transactionCommit()
    {
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionRollback()
     */
    public function transactionRollback()
    {
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionIsStarted()
     */
    public function transactionIsStarted()
    {
        return false;
    }
}