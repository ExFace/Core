<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;

interface iUseInputWidget extends WidgetInterface
{
    /**
     * Returns the widget, that supplies the input data for the action
     *
     * @return AbstractWidget $widget
     */
    public function getInputWidget();
    
    /**
     * Sets the widget, that supplies the input data for the action
     *
     * @param AbstractWidget $widget
     * @return AbstractWidget
     */
    public function setInputWidget(AbstractWidget $widget);
}
