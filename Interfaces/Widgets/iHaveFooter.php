<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveFooter extends WidgetInterface
{
    /**
     * @return boolean
     */
    public function getHideFooter() : ?bool;
    
    /**
     * 
     * @param boolean $boolean
     * @return iHaveFooter
     */
    public function setHideFooter($boolean) : iHaveFooter;
}