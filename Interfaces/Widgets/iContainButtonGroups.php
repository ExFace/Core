<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\CommonLogic\UxonObject;

interface iContainButtonGroups extends iHaveChildren
{
    /**
     * @return ButtonGroup[]
     */
    public function getButtonGroups();
    
    /**
     * 
     * @param ButtonGroup[]|UxonObject[] $widget_or_uxon_objects
     * @return iContainButtonGroups
     */
    public function setButtonGroups(array $widget_or_uxon_objects);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @return \exface\Core\Widgets\Toolbar
     */
    public function addButtonGroup(ButtonGroup $button_group);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @return iContainButtonGroups
     */
    public function removeButtonGroup(ButtonGroup $button_group);
}