<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iAmResizable extends WidgetInterface
{

    /**
     * Returs TRUE if the widget is resizable, FALSE otherwise
     *
     * @return boolean
     */
    public function getResizable();

    /**
     * Defines if widget shall be resizable (TRUE) or not (FALSE)
     *
     * @param boolean $value            
     * @return boolean
     */
    public function setResizable($value);
}