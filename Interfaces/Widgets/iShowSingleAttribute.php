<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Widgets implementig this interface can reference a single attribute from the meta model.
 * 
 * Typically these are all kinds of value widgets (inpus and displays), but also columns 
 * of data widgets.
 * 
 * The reference is optional: the widget may show an attribute, but may also show something
 * else (typically using a formula). Use the isBoundToAttribute() to determine, wether
 * an attribute is used.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowSingleAttribute extends WidgetInterface
{

    /**
     *
     * @return MetaAttributeInterface
     */
    public function getAttribute();

    /**
     *
     * @return string
     */
    public function getAttributeAlias();
    
    /**
     * Returns TRUE if the attribute reference is set for this specific widget instance and FALSE otherwise.
     * 
     * NOTE: this will also return TRUE if the reference is set, but incorrect (e.g. an non-existant alias).
     * 
     * @return boolean
     */
    public function isBoundToAttribute();
}