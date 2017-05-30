<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Exception thrown if a data connection could not be found in the meta model.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataConnectionNotFoundError extends UnexpectedValueException
{

    public function getDefaultAlias()
    {
        return '6V6EAL3';
    }
}
?>