<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Exceptions\DataSourceExceptionInterface;

/**
 * Exception thrown if a data source has no connection, but requires one.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataSourceHasNoConnectionError extends LogicException implements DataSourceExceptionInterface
{
    use DataSourceExceptionTrait;
    
    public function getDefaultLogLevel(){
        return LoggerInterface::CRITICAL;
    }
}