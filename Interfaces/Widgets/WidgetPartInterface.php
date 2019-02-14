<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;

/**
 * A widget part is a UXON-configurable subelement of a widget, which is not a widget itself.
 * 
 * In contrast to sub-widgets, widget parts are only known to their owner widget and cannot
 * be used stand-alone or referenced from outside (e.g. via widget links). They are also not 
 * part of the widget tree of a page. Widget parts share visibility, object-binding, 
 * disabled-state, etc. with their owner widget.
 * 
 * Widget parts greatly simplify sharing complex features across widgets. Similarly to behavioral
 * interfaces and corresponding traits (e.g. iHaveIcon, etc.), they standardize certain features. 
 * 
 * However, in contrast to the traits, widget parts group UXON properties related to their feature
 * visibly. While traits, add certain UXON properties to a widget, parts add entire (possibly complex)
 * substructures with many properties. E.g. if the UI designer needs to configure the calendar items 
 * for a calendar-related widget, which uses the `DataCalendarItem` part, all the required 
 * configuration will be done within a single UXON property (sub-object).
 * 
 * @author Andrej Kabachnik
 *        
 */
interface WidgetPartInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function getWidget() : WidgetInterface;    
}