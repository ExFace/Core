<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown on configuration errors in data sheet mappers.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataMapperConfigurationError extends LogicException implements DataMapperExceptionInterface {
    
    private $mapper = null;
    
    public function __construct(DataSheetMapperInterface $mapper, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->mapper = $mapper;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface::getMapper()
     */
    public function getMapper() : DataSheetMapperInterface
    {
        return $this->mapper;
    }
}