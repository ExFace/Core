<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Widgets\Dialog;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowDialog extends ActionInterface
{

    public function getDialogWidget();
    
    /**
     * Returns TRUE if the opened dialog should be maximized, FALSE if not and NULL if
     * no specific behavior was specified.
     *
     * @return boolean|mixed
     */
    public function getMaximize($default = false);
    
    /**
     * Set to TRUE to maximize the dialog when opened.
     *
     * If not set, every template will use it's own defaults.
     *
     * @uxon-property maximize
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     */
    public function setMaximize($true_or_false);

}