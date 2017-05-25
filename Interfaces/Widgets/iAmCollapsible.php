<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iAmCollapsible extends WidgetInterface
{

    /**
     * Returs TRUE if the widget is collapsible, FALSE otherwise
     * 
     * @return boolean
     */
    public function getCollapsible();

    /**
     * Defines if widget shall be collapsible (TRUE) or not (FALSE)
     * 
     * @param boolean $value            
     * @return boolean
     */
    public function setCollapsible($value);
}