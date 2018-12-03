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
    public function isCollapsible() : bool;

    /**
     * Defines if widget shall be collapsible (TRUE) or not (FALSE)
     *
     * @param boolean $value            
     * @return boolean
     */
    public function setCollapsible($value) : iAmCollapsible;
    
    /**
     * 
     * @return bool
     */
    public function isCollapsed() : bool;
    
    /**
     * 
     * @param bool|string $trueOrFalse
     * @return iAmCollapsible
     */
    public function setCollapsed($trueOrFalse) : iAmCollapsible;
}