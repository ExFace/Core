<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveFooter extends WidgetInterface
{
    /**
     * @return boolean
     */
    function getHideFooter();
    
    /**
     * 
     * @param boolean $boolean
     * @return iHaveFooter
     */
    function setHideFooter($boolean);
}