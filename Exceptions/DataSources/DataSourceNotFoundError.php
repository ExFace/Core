<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if a data source could not be found in the meta model.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSourceNotFoundError extends UnexpectedValueException implements NotFoundExceptionInterface
{

    public function getDefaultAlias()
    {
        return '6T4R97R';
    }
}
?>