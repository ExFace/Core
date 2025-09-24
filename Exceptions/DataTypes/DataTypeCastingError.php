<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\ValueExceptionInterface;

/**
 * Exception thrown if a value cannot be casted to a data type.
 *
 * This exception should be thrown on errors in the DataType::cast() methods.
 * In contrast to casting errors, validation errors occur if a value can
 * be casted, but does not match the restrictions of the specific data
 * type model.
 * 
 * @see DataTypeValidationError
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTypeCastingError extends UnexpectedValueException implements ValueExceptionInterface
{
    private $value = null;
    public function __construct($message, $alias = null, $previous = null, $value = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->value = $value;
    }

    /**
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }
}