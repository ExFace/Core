<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface DataConnectorExceptionInterface
{

    /**
     *
     * @param DataConnectionInterface $connector            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(DataConnectionInterface $connector, $message, $code = null, $previous = null);

    /**
     *
     * @return DataConnectionInterface
     */
    public function getConnector();

    /**
     *
     * @param DataQueryInterface $query            
     */
    public function setConnector(DataConnectionInterface $connector);
}
