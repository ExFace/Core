<?php
namespace exface\Core\Widgets;

/**
 * A sidebar is a collapsible secondary content inside a bigger widget - e.g. for an AI chat, comments, etc.
 *     
 * @author Andrej Kabachnik
 *        
 */
class Sidebar extends Panel
{
    private $resizable = true;

    /**
     * 
     * @return Dialog
     */
    public function getDialog()
    {
        return $this->getParent();
    }


    /**
     * Set to FALSE to prevent users from changing the width of the side bar
     * 
     * @uxon-property resizable
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return \exface\Core\Widgets\Sidebar
     */
    public function setResizable(bool $trueOrFalse) : Sidebar
    {
        $this->resizable = $trueOrFalse;
        return $this;
    } 

    /**
     * 
     * @return bool
     */
    public function isResizable() : bool
    {
        return $this->resizable;
    }
}