<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveContextMenu extends WidgetInterface
{

    /**
     * Returs the name of the icon to be used
     *
     * @return boolean
     */
    public function getContextMenuEnabled();

    /**
     * If set, the widget will display the defined icon (if the template supports it, of course)
     *
     * @param boolean $value            
     * @return iHaveContextMenu
     */
    public function setContextMenuEnabled($value);
}