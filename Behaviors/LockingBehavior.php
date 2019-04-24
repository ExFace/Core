<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;

/**
 * This behavior locks it's object by calling configurable lock and unlock actions.
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
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeActionPerformedEvent::getEventName(), [$this, 'handleBeforeReadData']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    public function handleBeforeReadData(OnBeforeActionPerformedEvent $event)
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
        
        if ($task->isTriggeredByWidget() === false || $task->hasInputData() === false) {
            return;
        }
        if ($action instanceof iShowDialog) {
            $widget = $action->getWidget();
        } elseif ($action instanceof iReadData) {
            $widget = $task->getWidgetTriggeredBy();
        }
        
        if (($widget instanceof iHaveButtons) === false) {
            return;
        }
        
        $modifyingButtons = [];
        foreach ($widget->getButtons() as $btn) {
            if ($btn->hasAction() && $btn->getAction() instanceof iModifyData) {
                $modifyingButtons[] = $btn;
            }
        }
        
        if (empty($modifyingButtons) === true) {
            return;
        }
        
        $sheet = $task->getInputData();
        if ($sheet->hasUidColumn(true) === false) {
            return;
        }
        
        $this->getLockAction()->handle($task, $event->getTransaction());
        
        if ($widget instanceof Dialog) {
            $closeBtn = $widget->getCloseButton();
            if ($closeBtn->hasAction() === false) {
                $closeBtn->setAction($this->getUnlockAction());
            } else {
                throw new BehaviorRuntimeError($this->getObject(), 'Cannot add unlock-action to a close-button, that already has an action attached!');
            }
        }
        
        /*
        $uidConditions = $sheet->getFilters()->getConditions(function(ConditionInterface $cond){
            return $cond->getExpression()->isMetaAttribute() && $cond->getExpression()->getAttribute()->isUidForObject();
        });
        
        if (empty($uidConditions) === false) {
            
        }*/
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
        return $this->lockActionUxon;
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
        return $this->unlockActionUxon;
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