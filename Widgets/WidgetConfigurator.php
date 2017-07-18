<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iCanBeConfigured;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetConfigurator extends Tabs implements iConfigureWidgets
{
    private $widget = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::getWidget()
     */
    public function getWidget()
    {
        if (is_null($this->widget)){
            // TODO search recursively for a parent with iHaveConfigurator
            return $this->getParent();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::setWidget()
     */
    public function setWidget(iCanBeConfigured $widget)
    {
        $this->widget = $widget;
        return $this;
    }
}
?>