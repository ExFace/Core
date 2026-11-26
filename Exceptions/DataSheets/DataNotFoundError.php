<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if expected data is not found in the data source
 *
 * @author Andrej Kabachnik
 *        
 */
class DataNotFoundError extends DataSheetRuntimeError implements NotFoundExceptionInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 404) : int
    {
        return parent::getStatusCode($default);
    }
}