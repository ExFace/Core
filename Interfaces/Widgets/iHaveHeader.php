<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveHeader extends WidgetInterface
{
    /**
     * Returns TRUE if the header MUST be hidden, FALSE if it must be shown and NULL if it's up to the facade.
     * 
     * @return boolean|NULL
     */
    function getHideHeader() : ?bool;
    
    /**
     * 
     * @param boolean $boolean
     * @return iHaveHeader
     */
    function setHideHeader(bool $boolean) : iHaveHeader;
    
}