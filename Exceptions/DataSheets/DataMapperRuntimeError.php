<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;

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
    
    private $logbook;
    
    public function __construct(DataSheetMapperInterface $mapper, DataSheetInterface $fromSheet, $message, $alias = null, $previous = null, LogBookInterface $logbook = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->mapper = $mapper;
        $this->fromSheet = $fromSheet;
        $this->logbook = $logbook;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = parent::createDebugWidget($error_message);
        if ($this->logbook !== null) {
            $error_message = $this->logbook->createDebugWidget($error_message);
        }
        return $error_message;
    }
}