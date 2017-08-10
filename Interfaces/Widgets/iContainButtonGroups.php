<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;

interface iContainButtonGroups extends iHaveChildren
{
    /**
     * 
     * @param callable $filter_callback
     * 
     * @return ButtonGroup[]
     */
    public function getButtonGroups(callable $filter_callback = null);
    
    /**
     * 
     * @param ButtonGroup[]|UxonObject[] $widget_or_uxon_objects
     * @return iContainButtonGroups
     */
    public function setButtonGroups(array $widget_or_uxon_objects);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @param integer $index
     * 
     * @return \exface\Core\Widgets\Toolbar
     */
    public function addButtonGroup(ButtonGroup $button_group, $index = null);
    
    /**
     * Returns a new empty button group suitable for this container, but not yet
     * part of it.
     * 
     * @param UxonObject $uxon
     * @return ButtonGroup
     */
    public function createButtonGroup(UxonObject $uxon = null);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @return iContainButtonGroups
     */
    public function removeButtonGroup(ButtonGroup $button_group);
    
    /**
     * Returns the first (= main) button group in the toolbar.
     * 
     * If the toolbar is empty, a new empty button group will be created 
     * automatically via createButtonGroup().
     * 
     * If the alignment parameter is passed, the first buttong group with the
     * given alignment will be returned.
     *
     * @return ButtonGroup
     */
    public function getButtonGroupFirst($alignment = null);
    
    /**
     * Returns the index (position) of the given ButtonGroup in the toolbar (starting with 0).
     * 
     * @param ButtonGroup $button_group
     * 
     * @return integer
     */
    public function getButtonGroupIndex(ButtonGroup $button_group);
    
    /**
     * Returns the ButtonGroup with the given index (position) or NULL if there is no such index.
     * 
     * @param integer $index
     * 
     * @throws WidgetChildNotFoundError if a given index cannot be found
     * 
     * @return ButtonGroup
     */
    public function getButtonGroup($index);
}