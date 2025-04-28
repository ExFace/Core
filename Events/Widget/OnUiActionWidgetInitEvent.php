<?php

namespace exface\Core\Events\Widget;

use exface\Core\Actions\ShowWidget;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Event fired after the a UI action has initialized its widget: e.g. the root of a UI page, a Dialog, a PopUp, etc.
 * 
 * @event exface.Core.Widget.OnUiActionWidgetInit
 * 
 * @author Andrej Kabachnik
 */
class OnUiActionWidgetInitEvent extends AbstractWidgetEvent implements MetaObjectEventInterface, ActionEventInterface
{
    private $object = null;

    private $action = null;

    /**
     * Create an event for the initialized widget and the object it is being initialized for
     * 
     * The constructor requires an object explicitly because the widget might
     * not have instatiated its object. Widgets only instatiate their objects if
     * required because not every widget has an explicit object. Many use the
     * object of their parents or the object from a defined relation.
     * 
     * @param \exface\Core\Interfaces\WidgetInterface $widget
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     */
    public function __construct(WidgetInterface $widget, MetaObjectInterface $object, ActionInterface $action = null)
    {
        parent::__construct($widget);
        $this->object = $object;
        $this->action = $action;
    }

    /**
     * 
     * @see \exface\Core\Interfaces\Events\MetaObjectEventInterface::getObject()
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }

    /**
     * 
     * @see \exface\Core\Interfaces\Events\ActionEventInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        if ($this->action === null) {
            $widget = $this->getWidget();
            if (! $widget->hasParent()) {
                $this->action = ActionFactory::createFromPrototype(new ActionSelector($widget->getWorkbench(), ShowWidget::class));
                return $this->action;
            }
            $parent = $widget->getParent();
            if (! $parent instanceof iTriggerAction) {
                throw new RuntimeException('Cannot determine action for OnUiActionWidgetInit event');
            }
            $this->action = $parent->getAction();
        }
        return $this->action;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnUiActionWidgetInit';
    }
}