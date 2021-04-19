<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown a concurrent write attemt (racing condition) is detected.
 *
 * @author Andrej Kabachnik
 *        
 */
class ConcurrentWriteError extends DataSheetWriteError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6T6HZLF';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::NOTICE;
    }
}
