<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if an attempt is made to create a duplicate object.
 * 
 * @see \exface\Core\Exceptions\DataSheets\DataSheetDuplicatesError
 *
 * @author Andrej Kabachnik
 *        
 */
class DuplicateError extends RuntimeException
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\OutOfBoundsException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
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