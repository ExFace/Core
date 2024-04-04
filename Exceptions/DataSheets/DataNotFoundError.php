<?php
namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown if expected data is not found in the data source
 *
 * @author Andrej Kabachnik
 *        
 */
class DataNotFoundError extends DataSheetRuntimeError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode()
    {
        return 404;
    }
}