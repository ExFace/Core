<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Traits\WidgetLayoutTrait;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;

/**
 * A widget grid layouts contained widget is a responsive grid layout.
 * 
 * This is the base widgets for layouting containers like panels, forms,
 * widget groups, etc.
 *     
 * @author Andrej Kabachnik
 *        
 */
class WidgetGrid extends Container implements iLayoutWidgets
{
    use WidgetLayoutTrait;
    
    /**
     * Array of widgets to be placed in the group: mostly Value widgets, but any other kind is OK too.
     *
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Value[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        return parent::setWidgets($widget_or_uxon_array);
    }
}