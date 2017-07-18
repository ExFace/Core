<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iConfigureWidgets extends WidgetInterface
{   
    /**
     * @return iCanBeConfigured
     */
    public function getWidget();
    
    /**
     * @param WidgetInterface $widget
     * @return iConfigureWidgets
     */
    public function setWidget(iCanBeConfigured $widget);
    
}