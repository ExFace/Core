<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Events\DataSheetEvent;

/**
 * Attachable to DataSheetEvents, calls any action.
 * 
 * @author SFL
 *
 */
class CallActionBehavior extends AbstractBehavior
{

    private $objectEventAlias = null;

    private $action = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractBehavior::register()
     */
    public function register()
    {
        $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.' . $this->getObjectEventAlias(), array(
            $this,
            'callAction'
        ));
        $this->setRegistered(true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractBehavior::exportUxonObject()
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
     * 
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
        return $this->action;
    }

    /**
     * 
     * @uxon-property action
     * @uxon-type object
     * 
     * @param UxonObject|string $action
     * @return BehaviorInterface
     */
    public function setAction($action)
    {
        $this->action = ActionFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($action));
        return $this;
    }

    /**
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
        
        if ($this->getAction()) {
            $action = $this->getAction()->copy();
            $action->setInputDataSheet($data_sheet);
            $action->getResult();
        }
    }
}