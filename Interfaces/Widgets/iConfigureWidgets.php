<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\WidgetConfigurator;

/**
 * This interface makes a widget be a configurator for another widget.
 * 
 * @see WidgetConfigurator for a details explanation.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iConfigureWidgets extends WidgetInterface
{   
    /**
     * @return iHaveConfigurator
     */
    public function getWidgetConfigured();
    
    /**
     * @param WidgetInterface $widget
     * @return iConfigureWidgets
     */
    public function setWidgetConfigured(iHaveConfigurator $widget);
    
}