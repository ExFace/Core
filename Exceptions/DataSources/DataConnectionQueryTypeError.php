<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;

/**
 * Exception thrown if an unsupported DataQuery type was passed to the DataConnecto::query() method.
 *
 * It is best practice for data connectors to always check if a supported query object was passed before
 * trying to deal with it! Similar query objects could produce strange effects otherwise, that would be
 * hard to debug.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataConnectionQueryTypeError extends InvalidArgumentException implements DataConnectorExceptionInterface
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

    public function getDefaultAlias()
    {
        return '6T5W75J';
    }
}
?>