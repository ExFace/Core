<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\TaskResultDataInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultData extends TaskResultMessage implements TaskResultDataInterface
{
    private $dataSheet = null;
    
    public function __construct(TaskInterface $task, DataSheetInterface $dataSheet)
    {
        parent::__construct($task);
        $this->setData($dataSheet);
    }
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::getData()
     */
    public function getData(): DataSheetInterface
    {
        if (is_null($this->dataSheet)) {
            $this->dataSheet = $this->getTask()->getInputData();
        }
        return $this->dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultDataInterface::setData()
     */
    public function setData(DataSheetInterface $dataSheet): TaskResultDataInterface
    {
        $this->dataSheet = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultDataInterface::hasData()
     */
    public function hasData(): bool
    {
        return is_null($this->dataSheet) ? false : true;
    }
}