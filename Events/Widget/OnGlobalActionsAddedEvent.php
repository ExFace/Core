<?php
namespace exface\Core\Events\Widget;

use exface\Core\Widgets\ButtonGroup;

/**
 * Event fired after global actions were added to a toolbar.
 * 
 * The event can be used to add additional global actions - e.g. in GlobalActionBehavior. The event
 * is fired for the `ButtonGroup` widget, that contains the global actions buttons.
 * 
 * @event exface.Core.Widget.OnGlobalActionsAdded
 *
 * @author Andrej Kabachnik
 *        
 */
class OnGlobalActionsAddedEvent extends AbstractWidgetEvent
{
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnGlobalActionsAdded';
    }

    /**
     * 
     * @return ButtonGroup
     */
    public function getGlobalActionsButtongGroup() : ButtonGroup
    {
        return $this->getWidget();
    }
}