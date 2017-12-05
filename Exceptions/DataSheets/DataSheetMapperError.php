<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Interfaces\Exceptions\DataSheetMapperExceptionInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * Exception thrown on general errors in data sheet mappers.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetMapperError extends RuntimeException implements DataSheetMapperExceptionInterface {
    
    private $mapper = null;
    
    use ExceptionTrait;
    
    public function __construct(DataSheetMapperInterface $mapper, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMapper($mapper);
    }
    
    /**
     *
     * @return DataSheetMapperInterface
     */
    public function getMapper()
    {
        return $this->data_sheet;
    }
    
    /**
     *
     * @param DataSheetMapperInterface $sheet
     * @return DataSheetMapperError
     */
    public function setMapper(DataSheetMapperInterface $mapper)
    {
        $this->data_sheet = $mapper;
        return $this;
    }
    
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        // TODO
        return $debug_widget;
    }
}