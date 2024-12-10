<?php

namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Event fired when a DataConfigurator widget is initialized
 * 
 * This allows behaviors like the WidgetModifyingBehavior to add their options to the 
 * configrator.
 * 
 * NOTE: the event contains the meta object, that was used to initialize the widget.
 * If the widgets object is changed later, it will obviously be different from that
 * initial object. So there might be a difference between `$event->getObject()` and
 * `$event->getWidget()->getMetaObject()`.
 * 
 * @author Andrej Kabachnik
 */
class OnDataConfiguratorInitEvent extends AbstractWidgetEvent implements MetaObjectEventInterface
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
        return 'exface.Core.Widget.OnDataConfiguratorInit';
    }
}