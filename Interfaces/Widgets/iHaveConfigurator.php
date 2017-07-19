<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\WidgetConfigurator;

/**
 * Widgets with this interface have a separate configurator widget, that controls their behavior.
 * 
 * @see WidgetConfigurator for a details explanation.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveConfigurator extends WidgetInterface
{
    /**
     * Sets the configurator widget
     * 
     * @param UxonObject|iConfigureWidgets $widget
     * @return iHaveConfigurator
     */
    public function setConfiguratorWidget($widget_or_uxon_object);
    
    /**
     * Returns the configurator of this widget
     * 
     * @return iConfigureWidgets
     */
    public function getConfiguratorWidget();
    
    /**
     * Returns the default widget type for configurators of this widget.
     * 
     * @return string
     */
    public function getConfiguratorWidgetType();
}