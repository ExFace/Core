<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Widgets implementig this interface can reference a single attribute from the meta model.
 * 
 * Typically these are all kinds of value widgets (inputs and displays), but also columns 
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
interface iShowSingleAttribute extends WidgetInterface
{

    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getAttribute() : ?MetaAttributeInterface;

    /**
     *
     * @return string|NULL
     */
    public function getAttributeAlias();
    
    /**
     * Returns TRUE if the attribute reference is set for this specific widget instance and FALSE otherwise.
     * 
     * This only returns TRUE if the widget is bound to a single attribute directly - not if the attribute
     * is part of a formula or similar. If a widget shows the result of a formula containing attributes,
     * it is not concidered bound to an attribute, but still is bound to a data column (if it implements 
     * the interface `iShowDataColumn` of course).
     * 
     * NOTE: this will also return TRUE if the reference is set, but incorrect (e.g. an non-existant alias).
     * 
     * @return boolean
     */
    public function isBoundToAttribute() : bool;
}