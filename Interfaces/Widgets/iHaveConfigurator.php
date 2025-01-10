<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Widgets with this interface have a separate configurator widget, that controls their behavior.
 * 
 * @see \exface\Core\Widgets\WidgetConfigurator for a details explanation.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveConfigurator extends WidgetInterface
{
    /**
     * Sets the configurator widget
     * 
     * @param iConfigureWidgets $widget
     * @return iHaveConfigurator
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator;
    
    /**
     * Returns the configurator of this widget
     * 
     * @return iConfigureWidgets
     */
    public function getConfiguratorWidget() : iConfigureWidgets;
    
    /**
     * Returns the default widget type for configurators of this widget.
     * 
     * @return string
     */
    public function getConfiguratorWidgetType() : string;
}