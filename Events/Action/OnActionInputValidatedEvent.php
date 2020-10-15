<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Event fired after an action has determined and validated it's input data.
 * 
 * This event allows to hook-in further validation handlers or even modify the
 * input data sheet if required.
 * 
 * @event exface.Core.Action.OnActionInputValidated
 *
 * @author Andrej Kabachnik
 *        
 */
class OnActionInputValidatedEvent extends AbstractActionEvent implements TaskEventInterface, DataSheetEventInterface
{
    private $dataSheet = null;
    
    private $task = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @param DataSheetInterface $inputData
     */
    public function __construct(ActionInterface $action, TaskInterface $task, DataSheetInterface $inputData)
    {
        parent::__construct($action);
        $this->task = $task;
        $this->dataSheet = $inputData;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\TaskEventInterface::getTask()
     */
    public function getTask() : TaskInterface
    {
        return $this->task;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Action.OnActionInputValidated';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataSheetEventInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
    {
        return $this->dataSheet;
    }
}