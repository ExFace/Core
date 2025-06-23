<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Widgets implementig this interface can reference a single attribute from the meta model.
 * 
 * Typically, these are all kinds of value widgets (inputs and displays), but also columns
 * of data widgets.
 * 
 * The reference is optional: the widget may show an attribute, but may also show something
 * else (typically using a formula). Use the isBoundToAttribute() to determine, wether
 * an attribute is used.
 * 
 * Most widgets, that are bound to attributes are also bound to data columns (i.e. implement
 * the `IShowDataColumn` interface), but there are some exceptions like various Filter widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowSingleAttribute extends WidgetInterface, iCanBeBoundToAttribute
{
    /**
     * Returns TRUE if this column has an attribute alias ending with __LABEL and FALSE otherwise.
     * 
     * @return bool
     */
    public function isBoundToLabelAttribute() : bool;
}