<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

interface iCanBeConfigured extends WidgetInterface
{
    /**
     * 
     * @param UxonObject|iConfigureWidgets $widget
     * @return iCanBeConfigured
     */
    public function setConfiguratorWidget($widget_or_uxon_object);
    
    /**
     * 
     * @return iConfigureWidgets
     */
    public function getConfiguratorWidget();
    
    /**
     * @return string
     */
    public function getConfiguratorWidgetType();
}