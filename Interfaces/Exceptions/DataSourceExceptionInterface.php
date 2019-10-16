<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataSourceInterface;

/**
 * Interface for exceptions in data sources.
 * 
 * @author Adnrej Kabachnik
 *
 */
interface DataSourceExceptionInterface extends ExceptionInterface
{
    public function getDataSource() : DataSourceInterface;
}
