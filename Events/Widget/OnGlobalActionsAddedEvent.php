<?php
namespace exface\Core\Events\Widget;

use exface\Core\Widgets\ButtonGroup;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;

/**
 * Event fired after global actions were added to a toolbar.
 * 
 * The event can be used to add additional global actions - e.g. in GlobalActionBehavior
 * 
 * @event exface.Core.Widget.OnGlobalActionsAdded
 *
 * @author Andrej Kabachnik
 *        
 */
class OnGlobalActionsAddedEvent extends AbstractWidgetEvent
{
    private $btnGroup = null;
    
    public function __construct(WidgetInterface $widget, iHaveButtons $globalActionsButtonGroup)
    {
        parent::__construct($widget);
        $this->btnGroup = $globalActionsButtonGroup;
    }
    
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
        return $this->btnGroup;
    }
}