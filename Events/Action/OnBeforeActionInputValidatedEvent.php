<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Event fired before an actions input data is validated, but after it was computed and mappers were run.
 * 
 * This event allows to hook-in further pre-validation handlers or even modify the
 * input data sheet if required.
 * 
 * @event exface.Core.Action.OnBeforeActionInputValidated
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeActionInputValidatedEvent extends AbstractActionEvent implements TaskEventInterface, DataSheetEventInterface
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
        return 'exface.Core.Action.OnBeforeActionInputValidated';
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