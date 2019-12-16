<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Toolbar;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveToolbars extends WidgetInterface
{
    /**
     * @return Toolbar[]
     */
    public function getToolbars();
    
    /**
     * 
     * @return bool
     */
    public function hasToolbars() : bool;
    
    /**
     * 
     * @param Toolbar[]|UxonObject $widget_or_uxon_objects
     * @return iHaveToolbars
     */
    public function setToolbars($widget_or_uxon_objects);
    
    /**
     *
     * @param Toolbar $toolbar
     * @return \exface\Core\Widgets\Toolbar
     */
    public function addToolbar(Toolbar $toolbar);
    
    /**
     *
     * @param Toolbar $button_group
     * @return iHaveToolbars
     */
    public function removeToolbar(Toolbar $toolbar);
    
    /**
     * Returns the default widget types for toolbars
     * 
     * @return string
     */
    public function getToolbarWidgetType();
}