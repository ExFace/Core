<?php

namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveContextualHelp extends WidgetInterface
{

    /**
     *
     * @return iTriggerAction
     */
    public function getHelpButton();

    /**
     * Fills the given container to build up context-sensitive help for an end-user.
     *
     * What exactly belongs into a help container depends on the specific widget type.
     *
     * @param iContainOtherWidgets $help_container            
     * @return WidgetInterface
     */
    public function getHelpWidget(iContainOtherWidgets $help_container);

    /**
     *
     * @return boolean
     */
    public function getHideHelpButton();

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\Widgets\iHaveContextualHelp
     */
    public function setHideHelpButton($value);
}