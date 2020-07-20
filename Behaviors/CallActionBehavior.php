<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\Events\TaskEventInterface;

/**
 * Attachable to DataSheetEvents (exface.Core.DataSheet.*), calls any action.
 * 
 * For this behavior to work, it has to be attached to an object in the metamodel. The event-
 * alias and the action have to be configured in the behavior configuration.
 * 
 * Example:
 * 
 * ```
 * {
 *  "event_alias": "exface.Core.DataSheet.OnBeforeCreateData",
 *  "action": {
 *      "alias": "..."
 *  }
 * }
 * 
 * ```
 * 
 * @author SFL
 *
 */
class CallActionBehavior extends AbstractBehavior
{

    private $objectEventAlias = null;

    private $action = null;
    
    private $actionConfig = null;
    
    private $priority = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $handler = [$this, 'callAction'];
        $this->getWorkbench()->eventManager()->addListener($this->getEventAlias(), $handler, $this->getPriority());
        $this->setRegistered(true);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('event_alias', $this->getEventAlias());
        $uxon->setProperty('action', $this->getAction()->exportUxonObject());
        return $uxon;
    }

    /**
     * 
     * @return string
     */
    public function getEventAlias() : string
    {
        return $this->event_alias;
    }

    /**
     * Alias of the event, that should trigger the action.
     * 
     * Technically, any type of event selector will do - e.g. 
     * - `exface.Core.DataSheet.OnBeforeCreateData`
     * - `\exface\Core\Events\DataSheet\OnBeforeCreateData`
     * - OnBeforeCreateData::class (in PHP)
     * 
     * @uxon-property event_alias
     * @uxon-type metamodel:event
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return CallActionBehavior
     */
    public function setEventAlias(string $aliasWithNamespace) : CallActionBehavior
    {
        $this->event_alias = $aliasWithNamespace;
        return $this;
    }

    /**
     * 
     * @return ActionInterface
     */
    public function getAction()
    {
        if ($this->action === null) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($this->actionConfig));
            try {
                $this->action->getMetaObject();
            } catch (ActionObjectNotSpecifiedError $e) {
                $this->action->setMetaObject($this->getObject());
            }
        }
        return $this->action;
    }

    /**
     * Sets the action which is executed upon the configured event.
     * 
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * @uxon-required true
     * 
     * @param UxonObject|string $action
     * @return BehaviorInterface
     */
    public function setAction($action)
    {
        $this->actionConfig = $action;
        return $this;
    }

    /**
     * The method which is called when the configured event is fired, which executes the
     * configured action. 
     * 
     * @param DataSheetEventInterface $event
     */
    public function callAction(DataSheetEventInterface $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $event->getDataSheet()->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if ($action = $this->getAction()) {
            if ($event instanceof TaskEventInterface) {
                $task = $event->getTask();
                $task->setInputData($data_sheet);
            } else {
                // We never have an input widget here, so tell the action it won't get one
                // and let it deal with it.
                $action->setInputTriggerWidgetRequired(false);
                $task = TaskFactory::createFromDataSheet($data_sheet);
            }
            if ($event instanceof DataTransactionEventInterface) {
                $action->handle($task, $event->getTransaction());
            } else {
                $action->handle($task);
            }
        }
    }
    
    /**
     *
     * @return int|NULL
     */
    public function getPriority() : ?int
    {
        return $this->priority;
    }
    
    /**
     * Event handlers with higher priority will be executed first!
     * 
     * @uxon-property priority
     * @uxon-type integer
     * 
     * @param int $value
     * @return CallActionBehavior
     */
    public function setPriority(int $value) : CallActionBehavior
    {
        $this->priority = $value;
        return $this;
    }
}