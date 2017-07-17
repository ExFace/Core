<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Widgets\Container;

/**
 * 
 * @method Container getResult()
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowPopup extends ActionInterface
{

    public function getPopupContainer();

}