<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iCanBeConfigured extends WidgetInterface
{
    /**
     * 
     * @param iConfigureWidgets $widget
     * @return iCanBeConfigured
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget);
    
    /**
     * 
     * @return iConfigureWidgets
     */
    public function getConfiguratorWidget();
}