<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Factories\TaskFactory;

/**
 * Attachable to DataSheetEvents, calls any action.
 * 
 * For this behavior to work, it has to be attached to an object in the metamodel. The event-
 * alias and the action have to be configured in the behavior configuration.
 * 
 * @author SFL
 *
 */
class CallActionBehavior extends AbstractBehavior
{

    private $objectEventAlias = null;

    private $action = null;
    
    private $actionConfig = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.' . $this->getObjectEventAlias(), array(
            $this,
            'callAction'
        ));
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
        $uxon->setProperty('object_event_alias', $this->getObjectEventAlias());
        $uxon->setProperty('action', $this->getAction()->exportUxonObject());
        return $uxon;
    }

    /**
     * 
     * @return string
     */
    public function getObjectEventAlias()
    {
        return $this->object_event_alias;
    }

    /**
     * Sets the event alias upon which the configured action is executed
     * (e.g. 'DataSheet.CreateData.After').
     * 
     * @uxon-property object_event_alias
     * @uxon-type string
     * 
     * @param string $objectEventAlias
     * @return BehaviorInterface
     */
    public function setObjectEventAlias($objectEventAlias)
    {
        $this->object_event_alias = $objectEventAlias;
        return $this;
    }

    /**
     * 
     * @return ActionInterface
     */
    public function getAction()
    {
        if (is_null($this->action)) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($this->actionConfig));
        }
        return $this->action;
    }

    /**
     * Sets the action which is executed upon the configured event.
     * 
     * @uxon-property action
     * @uxon-type object
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
     * @param DataSheetEvent $event
     */
    public function callAction(DataSheetEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if ($action = $this->getAction()) {
            $task = TaskFactory::createFromDataSheet($data_sheet);
            // FIXME #events-v2 pass the transaction to the action once it is available in the data sheet event
            $action->handle($task);
        }
    }
}