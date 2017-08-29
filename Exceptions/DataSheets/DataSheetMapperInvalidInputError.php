<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Exception thrown if a data sheet mapper receives unusable input data.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetMapperInvalidInputError extends UnexpectedValueException
{
    use DataSheetMapperExceptionTrait;
}
?>