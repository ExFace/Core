<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;

/**
 * Exception thrown if a data type fails to read it's configuration or an invalid configuration value is passed.
 *
 * This exception will be typically thrown by setters in the data type class. This way, configuration values being
 * set programmatically and via UXON import can be checked in the same manner.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTypeConfigurationError extends UnexpectedValueException implements DataTypeExceptionInterface
{
    use DataTypeExceptionTrait;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataType($dataType);
    }
}
