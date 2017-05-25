<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Widgets\Dialog;

interface iShowDialog
{

    public function getDialogWidget();

    /**
     * The output of an action showing a widget is the widget instance
     * 
     * @return Dialog
     */
    public function getResult();
}