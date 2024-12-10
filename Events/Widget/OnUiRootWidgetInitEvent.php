<?php

namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Event fired after the root widget of a UI view is initialized: e.g. the root of a UI page, a Dialog, a PopUp, etc.
 * 
 * @event exface.Core.Widget.OnUiRootWidgetInit
 * 
 * @author Andrej Kabachnik
 */
class OnUiRootWidgetInitEvent extends AbstractWidgetEvent implements MetaObjectEventInterface
{
    private $object = null;

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
    public function __construct(WidgetInterface $widget, MetaObjectInterface $object)
    {
        parent::__construct($widget);
        $this->object = $object;
    }

    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnUiRootWidgetInit';
    }
}