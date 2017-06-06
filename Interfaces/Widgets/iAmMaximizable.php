<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iAmMaximizable extends WidgetInterface
{
    
    /**
     * 
     * @param boolean $value
     * @return iAmMaximizable
     */
    function setMaximizable($value);
    
    /**
     * @return boolean
     */
    function isMaximizable();

    /**
     *
     * @param boolean $value
     * @return iAmMaximizable
     */
    function setMaximized();
    
    /**
     * @return boolean
     */
    function isMaximized();
}