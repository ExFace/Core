<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * Exception thrown if the extraction of rows from a data sheet fails.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetExtractError extends DataSheetRuntimeError
{
    private $conditionGroup = null;
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param string $message
     * @param string|NULL $alias
     * @param \Throwable|NULL $previous
     * @param ConditionGroupInterface $filter
     */
    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null, ConditionGroupInterface $filter = null)
    {
        parent::__construct($data_sheet, $message, $alias, $previous);
        $this->conditionGroup = $filter;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    public function getExtractionFilter() : ?ConditionGroupInterface
    {
        return $this->conditionGroup;
    }
}