<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Exception thrown if a data mapper receives incompatible input-data.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMapperRuntimeError extends RuntimeException implements DataMapperExceptionInterface
{
    private $mapper = null;
    
    private $fromSheet = null;
    
    public function __construct(DataSheetMapperInterface $mapper, DataSheetInterface $fromSheet, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->mapper = $mapper;
        $this->fromSheet = $fromSheet;
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
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getFromSheet() : DataSheetInterface
    {
        return $this->fromSheet;
    }
}