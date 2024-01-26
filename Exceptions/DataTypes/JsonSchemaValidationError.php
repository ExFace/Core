<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\UnexpectedValueException;

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
class JsonSchemaValidationError extends UnexpectedValueException
{
    private $errors = [];
    
    private $exception = [];
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct(array $validationErrors, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->errors = $validationErrors;
    }
    
    public function addError(string $message) : JsonSchemaValidationError
    {
        $this->errors[] = $message;
        return $this;
    }
    
    public function addException(\Throwable $e) : JsonSchemaValidationError
    {
        
    }
    
    public function getValidationErrorMessages() : array
    {
        $msgs = $this->errors;
        foreach ($this->exception as $ex) {
            $this->errors[] = $ex->getMessage();
        }
        return $msgs;
    }
}
?>