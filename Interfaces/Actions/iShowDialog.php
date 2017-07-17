<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Widgets\Dialog;

/**
 * 
 * @method Dialog getResult()
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowDialog extends ActionInterface
{

    public function getDialogWidget();

}