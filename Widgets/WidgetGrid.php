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
}
?>