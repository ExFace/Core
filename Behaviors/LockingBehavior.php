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
use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\Interfaces\Actions\iCreateData;

/**
 * This behavior locks it's object by calling configurable lock and unlock actions.
 * 
 * This behavior performs a lock action every time data of it's object read for modification: e.g.
 * 
 * - When a form, editable table or any other widget with buttons performing modifying actions is prefilled
 * - When a dialog is opened with buttons, that can potentially modify the data 
 * 
 * After the data had been modified, or the editor was dismissed (e.g. the dialog closed), the unlock 
 * action is performed automatically to release the locks.
 * 
 * This behavior can be used to lock and unlock objects in their data source. The locking type (e.g.
 * pessimistic, optimistic locking, etc.) is completely upto the the actions taken in the data source. The
 * behavior only takes care of triggering them whenever a modification is possible.
 * 
 * **NOTE**: currently this behavior works well with dialogs, as with dialogs, it's obvious, when to
 * unlock the data - when the dialog is closed. Editors outside of dialogs will lock their data, but
 * will need dedicated buttons to unlock it (just a button with the unlock action). At the moment, there
 * is no way to notify the behavior, when the user navigates away from the editor.
 * 
 * ## How to use
 * 
 * Add a behavior to the object, that should be locked with `LockingBehavior` as prototype and
 * a configuration as follows:
 * 
 * ```
 * {
 *  "lock_action": {
 *      "alias": "my.App.LockMyObject"
 *  },
 *  "unlock_action": {
 *      "alias": "my.App.UnlockMyObject"
 *  }
 * }
 * 
 * ```
 * 
 * If you want to add other actions, that require locking (e.g. a remote function call, that
 * cannot be automatically identifying as lock-worthy):
 * 
 * ```
 * {
 *  "lock_action": {
 *      "alias": "my.App.LockMyObject"
 *  },
 *  "unlock_action": {
 *      "alias": "my.App.UnlockMyObject"
 *  },
 *  "lock_for_actions": [
 *      "my.App.CustomAction1",
 *      "my.App.CustomAction2"
 *  ]
 * }
 * 
 * ```
 * 
 * ## How exactly is the data being locked and unlocked
 * 
 * After data had been read (via an action implementing the `iReadData` interface), it will
 * be passed to the lock action if
 * 
 * - it was read for a widget, that has modifying buttons
 * - it was read for a widget, that opens a dialog, that has modifying buttons
 * 
 * A button is concidered modifying if it has an action and that action 
 * - implements the interface `iModifyData`, but does not implement `iDeleteData` or `iCreateData`
 * - is in the list of action aliases defined in `lock_for_actions` or is a derivative of one of
 * these actions.
 * 
 * @author Andrej Kabachnik
 *
 */
class LockingBehavior extends AbstractBehavior
{
    private $lockActionUxon = null;
    
    private $unlockActionUxon = null;
    
    private $actionsToLockFor = [];
    
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
            if ($btn->hasAction() && $this->needsLock($btn->getAction()) === true) {
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
    
    /**
     * Returns TRUE a lock should be put on data, which is being read for the given action.
     * 
     * In general, a lock is required for action, which can modify an existing object. Among
     * the core action, that would be any action implementing `iModifyData`, but not those
     * creating or deleting data.
     * 
     * @param ActionInterface $action
     * @return bool
     */
    protected function needsLock(ActionInterface $action) : bool
    {
        if ($action instanceof iModifyData && ! ($action instanceof iDeleteData) && ! ($action instanceof iCreateData)) {
            return true;
        }
        
        foreach ($this->getLockForActionAliases() as $alias) {
            if ($action->is($alias) === true) {
                return true;
            }
        }
        
        return false;
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
        $action = ActionFactory::createFromUxon($this->getWorkbench(), $this->getLockActionUxon());
        $action->setInputTriggerWidgetRequired(false);
        return $action;
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
        $action = ActionFactory::createFromUxon($this->getWorkbench(), $this->getUnlockActionUxon());
        $action->setInputTriggerWidgetRequired(false);
        return $action;
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
    
    /**
     * 
     * @return string[]
     */
    protected function getLockForActionAliases() : array
    {
        return $this->actionsToLockFor;
    }
    
    /**
     * Array of action aliases, that require locking (in addition to standard modifying actions)
     * 
     * @uxon-property lock_for_actions
     * @uxon-type metamodel:action[]
     * @uxon-template [""]
     * 
     * @param array $uxonArray
     * @return LockingBehavior
     */
    public function setLockForActions(UxonObject $uxonArray) : LockingBehavior
    {
        $this->actionsToLockFor = $uxonArray->toArray();
        return $this;
    }
    
}