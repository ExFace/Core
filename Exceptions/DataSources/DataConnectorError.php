<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;

/**
 * This is the base class for all sorts of data source access errors like
 *
 * @see DataConnectionFailedError
 * @see DataConnectionCommitFailedError
 * @see DataConnectionRollbackFailedError
 * @see DataConnectionTransactionStartError
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class DataConnectorError extends RuntimeException implements DataConnectorExceptionInterface
{
    
    use DataConnectorExceptionTrait;

    /**
     *
     * @param DataConnectionInterface $connector            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(DataConnectionInterface $connector, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setConnector($connector);
    }
}
?>