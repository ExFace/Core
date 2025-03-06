<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;

interface iUseInputWidget extends WidgetInterface
{
    /**
     * Returns the widget, that supplies the input data for the action
     *
     * @return WidgetInterface
     */
    public function getInputWidget() : WidgetInterface;
    
    /**
     * Sets the widget, that supplies the input data for the action
     *
     * @param WidgetInterface $widget
     * @return iUseInputWidget
     */
    public function setInputWidget(WidgetInterface $widget) : iUseInputWidget;
}
