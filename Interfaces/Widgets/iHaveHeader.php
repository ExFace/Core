<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveHeader extends WidgetInterface
{
    /**
     * @return boolean
     */
    function getHideHeader();
    
    /**
     * 
     * @param boolean $boolean
     * @return iHaveHeader
     */
    function setHideHeader($boolean);
    
}