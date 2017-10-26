<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\RangeException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Exception thrown if a value does not fit a data type's model.
 *
 * This exception should be thrown on errors in the DataType::parse() methods.
 * If a value is so much different, that it even cannot be casted to a data
 * type, a DataTypeCastingError will be raised instead of a validation error.
 * 
 * @see DataTypeCastingError
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTypeValidationError extends RangeException
{
    use DataTypeExceptionTrait;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataType($dataType);
    }
    
    /**
     * Validation errors will use the error code from their data type model if defined.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RangeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        if ($code = $this->getDataType()->getValidationErrorCode()){
            return $code;
        }
        
        return parent::getDefaultAlias();
    }
}
?>