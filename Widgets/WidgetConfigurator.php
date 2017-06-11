<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\WidgetInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetConfigurator extends Tabs implements iConfigureWidgets
{
    private $widgetLinks = array();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::getWidget()
     */
    public function getWidget()
    {
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::setWidget()
     */
    public function setWidget(WidgetInterface $widget){
        
    }
}
?>