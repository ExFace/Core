<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * Common interface for actions that render and show widgets
 * 
 * @triggers \exface\Core\Events\Widgets\OnUiActionWidgetInitEvent after its widget was rendered
 */
interface iShowWidget extends iNavigate
{

    /**
     * 
     *
     * @throws ActionConfigurationError
     * 
     * @return WidgetInterface|NULL
     */
    public function getWidget();

    /**
     *
     * @param WidgetInterface|UxonObject|string $any_widget_source            
     */
    public function setWidget($any_widget_source) : iShowWidget;
    
    /**
     * Returns TRUE if the action has a widget to show at the moment and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isWidgetDefined() : bool;
    
    /**
     * Returns the default widget type, that this action will show: e.g. "Dialog" for ShowDialog-actions
     * 
     * @return string
     */
    public function getDefaultWidgetType() : ?string;
    
    /**
     * Returns the action to read prefill data for the widget.
     * 
     * @return ActionInterface
     */
    public function getPrefillAction() : ActionInterface;
}