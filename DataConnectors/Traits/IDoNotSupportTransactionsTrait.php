<?php
namespace exface\Core\DataConnectors\Traits;

/**
 * This trait adds stub methods to handle transaction commands to a connector, that does not support transactions
 * 
 * @author Andrej Kabachnik
 */
trait IDoNotSupportTransactionsTrait
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