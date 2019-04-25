<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

interface iHaveContextualHelp extends WidgetInterface
{

    /**
     *
     * @return iTriggerAction
     */
    public function getHelpButton() : iTriggerAction;

    /**
     * 
     * @return iHaveContextualHelp
     */
    public function setHelpButton(UxonObject $uxon) : iHaveContextualHelp;

    /**
     * Fills the given container to build up context-sensitive help for an end-user.
     *
     * What exactly belongs into a help container depends on the specific widget type.
     *
     * @param iContainOtherWidgets $help_container            
     * @return WidgetInterface
     */
    public function getHelpWidget(iContainOtherWidgets $help_container) : iContainOtherWidgets;

    /**
     *
     * @return boolean
     */
    public function getHideHelpButton() : bool;

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\Widgets\iHaveContextualHelp
     */
    public function setHideHelpButton(bool $value) : iHaveContextualHelp;
}