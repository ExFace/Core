<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Widgets\DialogButton;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

/**
 * This behavior locks it's object by calling configurable lock and unlock actions.
 * 
 * This behavior performs a lock action every time data of it's object read for modification: e.g.
 * 
 * - When a form, editable table or any other widget with buttons performing modifying actions is prefilled
 * - When a dialog is opened with buttons, that can potentially modify the data 
 * 
 * After the data is being modified, or the editing mode is dismissed (e.g. an editor dialog is
 * closed), the unlock action is performed automatically.
 * 
 * This behavior can be used to lock and unlock objects in their data source. The locking type (e.g.
 * pessimistic, optimistic locking, etc.) is completely upto the the actions taken in the data source. The
 * behavior only takes care of triggering them whenever a modification is possible.
 * 
 * **NOTE**: currently this behavior works well with dialogs, as it is clear, that a modification can only take
 * place as long as the dialog is opened. When using editable tables or forms outside of dialogs, locking
 * works well, but there is no trigger for unlocking.
 * 
 * @author Andrej Kabachnik
 *
 */
class LockingBehavior extends AbstractBehavior
{
    private $lockActionUxon = null;
    
    private $unlockActionUxon = null;
    
    public function register() : BehaviorInterface
    {
        
        $this->getWorkbench()->eventManager()->addListener(OnActionPerformedEvent::getEventName(), [$this, 'handleBeforeReadData']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    public function handleBeforeReadData(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        
        try {
            if ($action->getMetaObject()->is($this->getObject()) === false) {
                return;
            }
        } catch (ActionObjectNotSpecifiedError $e) {
            return;
        }
        
        $task = $event->getTask();
        $taskResult = $event->getResult();
        
        if ($task->isTriggeredByWidget() === false) {
            return;
        }
        
        if ($this->unlockOnEditorClose($action, $task, $event->getTransaction()) !== null) {
            return;
        }
        
        if ($action instanceof iShowDialog) {
            // When opening a dialog, we will need to lock the object shown (i.e. prefill data)
            $widget = $action->getWidget();
            $sheet = $widget->getPrefillData();
            if ($sheet !== null) {
                $this->lockOnEditorInit($widget, $sheet, $event->getTransaction());
            }
        } elseif ($action instanceof iReadData) {
            // When reading data, we need to lock the read objects in case the widget, that
            // requested the read, is able to modify the data
            $widget = $taskResult->getTask()->getWidgetTriggeredBy();
            if ($taskResult->hasData() === true) {
                $sheet = $taskResult->getData();
                $this->lockOnEditorInit($widget, $sheet, $event->getTransaction());
            }
        }
    }
    
    protected function unlockOnEditorClose(ActionInterface $action, TaskInterface $task, DataTransactionInterface $transaction) : ?ResultInterface
    {
        if ($action->isExactly($this->getUnlockAction()) === true) {
            return null;
        }
        
        $triggerWidget = null;
        if ($task->isTriggeredByWidget() === true) {
            $triggerWidget = $task->getWidgetTriggeredBy();
        } elseif ($action->isDefinedInWidget() === true) {
            $triggerWidget = $action->getWidgetDefinedIn();
        }
        
        if ($triggerWidget !== null) {
            if ($triggerWidget instanceof DialogButton && $triggerWidget->getCloseDialogAfterActionSucceeds() === true) {
                if ($task->hasInputData()) {
                    return $this->unlock($task->getInputData(), $transaction);
                }
            }
        }
        return null;
    }
    
    protected function addUnlockActionToDialogCloseButton(Dialog $widget)
    {
        $closeBtn = $widget->getCloseButton();
        if ($closeBtn->hasAction() === false) {
            $closeBtn->setAction($this->getUnlockActionUxon());
        } else {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot add unlock-action to a close-button, that already has an action attached!');
        }
    }
    
    protected function lockOnEditorInit(WidgetInterface $widget, DataSheetInterface $sheet, DataTransactionInterface $transaction) : ?ResultInterface
    { 
        if (($widget instanceof iHaveButtons) === false) {
            return null;
        }
        
        $modifyingButtons = [];
        foreach ($widget->getButtons() as $btn) {
            if ($btn->hasAction() && $btn->getAction() instanceof iModifyData) {
                $modifyingButtons[] = $btn;
            }
        }
        
        if (empty($modifyingButtons) === true) {
            return null;
        }
        
        if ($sheet === null || $sheet->hasUidColumn(true) === false) {
            return null;
        }
        
        $result = $this->lock($sheet, $transaction);
        
        if ($widget instanceof Dialog) {
            $this->addUnlockActionToDialogCloseButton($widget);
        }
        
        return $result;
    }
    
    protected function lock(DataSheetInterface $data, DataTransactionInterface $transaction) : ResultInterface
    {
        $task = new GenericTask($this->getWorkbench());
        $task->setInputData($data);
        return $this->getLockAction()->handle($task, $transaction);
    }
    
    protected function unlock(DataSheetInterface $data, DataTransactionInterface $transaction) : ResultInterface
    {
        $task = new GenericTask($this->getWorkbench());
        $task->setInputData($data);
        return $this->getUnlockAction()->handle($task, $transaction);
    }
    
    protected function getLockAction() : ActionInterface
    {
        return ActionFactory::createFromUxon($this->getWorkbench(), $this->getLockActionUxon());
    }

    /**
     *
     * @return string
     */
    protected function getLockActionUxon() : UxonObject
    {
        $uxon = $this->lockActionUxon;
        if ($uxon->isEmpty()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Required property lock_action not set for LockingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '!', '75DBQ3G');
        }
        return $uxon;
    }
    
    /**
     * The action to lock an instance of this object
     * 
     * @uxon-property lock_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param string $uxon
     * @return LockingBehavior
     */
    public function setLockAction(UxonObject $uxon) : LockingBehavior
    {
        $this->lockActionUxon = $uxon;
        return $this;
    }
    
    /**
     *
     * @return UxonObject
     */
    protected function getUnlockActionUxon() : UxonObject
    {
        $uxon = $this->unlockActionUxon;
        if ($uxon->isEmpty()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Required property unlock_action not set for LockingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '!', '75DBQ3G');
        }
        return $uxon;
    }
    
    /**
     * 
     * @return ActionInterface
     */
    protected function getUnlockAction() : ActionInterface
    {
        return ActionFactory::createFromUxon($this->getWorkbench(), $this->getUnlockActionUxon());
    }
    
    /**
     * The action to unlock an instance of this object
     * 
     * @uxon-property unlock_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param UxonObject $uxon
     * @return LockingBehavior
     */
    public function setUnlockAction(UxonObject $uxon) : LockingBehavior
    {
        $this->unlockActionUxon = $uxon;
        return $this;
    }
    
    
}