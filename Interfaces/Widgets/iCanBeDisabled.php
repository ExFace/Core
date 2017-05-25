<?php

namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iCanBeDisabled extends WidgetInterface
{

    /**
     * Returns TRUE if the widget is disabled (= no user interaction) and FALSE otherwise
     * 
     * @return boolean
     */
    public function isDisabled();

    /**
     * Disables the widget when set to TRUE and enables it with FALSE.
     * Users cannot interact with disabled widgets,
     * but other widgets can. Disabled widgets also deliver data to actions. To prevent this, make the widget
     * readonly.
     *
     * @param boolean $true_or_false            
     * @return WidgetInterface
     */
    public function setDisabled($true_or_false);
}