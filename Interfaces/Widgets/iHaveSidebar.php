<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Sidebar;

interface iHaveSidebar extends WidgetInterface
{
    /**
     * @return Sidebar
     */
    public function getSidebar() : Sidebar;

    /**
     *
     * @return bool
     */
    public function hasSidebar() : bool;
}