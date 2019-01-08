<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveBorders extends WidgetInterface
{

    /**
     * 
     * @return bool
     */
    public function getShowBorder() : bool;

    /**
     * 
     * @param bool $value
     * @return iHaveBorders
     */
    public function setShowBorder(bool $value) : iHaveBorders;
}