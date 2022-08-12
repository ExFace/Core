<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\ResultFactory;

/**
 * Allows to fire certain events (mainly related to data) and trigger their handlers explicitly.
 * 
 * The following events are supported:
 * 
 * - `exface.Core.DataSheet.OnCreateData`
 * - `exface.Core.DataSheet.OnUpdateData`
 * - `exface.Core.DataSheet.OnDeleteData`
 *
 * @author Andrej Kabachnik
 *        
 */
class CallEvent extends AbstractAction
{
    /**
     * 
     * @var string
     */
    private $eventName = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $eventMgr = $this->getWorkbench()->eventManager();
        $dataSheet = $this->getInputDataSheet($task);
        switch ($this->getEventName()) {
            case OnCreateDataEvent::getEventName():
                $eventMgr->dispatch(new OnCreateDataEvent($dataSheet, $transaction));
                break;
            case OnUpdateDataEvent::getEventName():
                $eventMgr->dispatch(new OnUpdateDataEvent($dataSheet, $transaction));
                break;
            case OnDeleteDataEvent::getEventName():
                $eventMgr->dispatch(new OnDeleteDataEvent($dataSheet, $transaction));
                break;
            default:
                throw new ActionConfigurationError($this, 'Cannot fire event "' . $this->getEventName() . '" via action "' . $this->getAliasWithNamespace() . '": this type of event is not supported!');
        }
        $result = ResultFactory::createDataResult($task, $dataSheet, 'Event "' . $this->getEventName() . '" fired.');
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isUndoable()
     */
    public function isUndoable() : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractAction::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('event', $this->getEventName());
        return $uxon;
    }
    
    /**
     * 
     * @return string
     */
    protected function getEventName() : string
    {
        return $this->eventName;
    }
    
    /**
     * The name of the event to be fired
     * 
     * @uxon-property event
     * @uxon-type [exface.Core.DataSheet.OnCreateData,exface.Core.DataSheet.OnUpdateData,exface.Core.DataSheet.OnDeleteData]
     * @uxon-required true
     * 
     * @param string $value
     * @return CallEvent
     */
    public function setEvent(string $value) : CallEvent
    {
        $this->eventName = $value;
        return $this;
    }
}