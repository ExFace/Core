<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
interface iShowPopup extends ActionInterface
{
    /**
     * 
     * @return iContainOtherWidgets
     */
    public function getPopupContainer() : iContainOtherWidgets;
}