<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;
use exface\Core\Interfaces\Exceptions\ValueExceptionInterface;

/**
 * Exception thrown if a value cannot be formatted according to its data type.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTypeFormattingError extends RuntimeException implements ValueExceptionInterface, DataTypeExceptionInterface
{
    use DataTypeExceptionTrait;
    
    private $value = null;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType, $message, $alias = null, $previous = null, $value = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataType($dataType);
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