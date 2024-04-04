<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a query fails due to a constraint violation within the data source.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryConstraintError extends DataQueryFailedError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSources\DataQueryFailedError::getDefaultLogLevel()
     */
    public function getDefaultLogLevel(){
        return LoggerInterface::ERROR;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '73II64M';
    }
}