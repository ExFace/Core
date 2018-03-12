<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultData extends ResultMessage implements ResultDataInterface
{
    private $dataSheet = null;
    
    public function __construct(TaskInterface $task, DataSheetInterface $dataSheet = null)
    {
        parent::__construct($task);
        if (is_null($dataSheet)) {
            $dataSheet = DataSheetFactory::createFromObject($task->getMetaObject());
        }
        $this->setData($dataSheet);
    }
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getData()
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
     * @see \exface\Core\Interfaces\Tasks\ResultDataInterface::setData()
     */
    public function setData(DataSheetInterface $dataSheet): ResultDataInterface
    {
        $this->dataSheet = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultDataInterface::hasData()
     */
    public function hasData(): bool
    {
        return is_null($this->dataSheet) ? false : true;
    }
}