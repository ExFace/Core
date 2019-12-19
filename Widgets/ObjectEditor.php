<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * An auto-generated form containing the default editor for the widget's object.
 * 
 * The content of the `ObjectEditor` is the same as that of the dialog from
 * `exface.Core.ShowObjectEditDalog` and similar actions. However, the `ObjectEditor`
 * does not include the buttons of the editor-dialog.
 * 
 * ## Example
 * 
 * This UXON will produce an editor-form for the data connection meta object from
 * the core model.
 * 
 * ```
 * {
 *  "widget_type": "ObjectEditor",
 *  "object_alias": "exface.Core.CONNECTION"
 * }
 * 
 * ```
 * 
 * If you need a button to save the connection, add it like this:
 * 
 * ```
 * {
 *  "widget_type": "ObjectEditor",
 *  "object_alias": "exface.Core.CONNECTION",
 *  "buttons": [
 *      "action_alias": "exface.Core.CreateData"
 *  ]
 * }
 * 
 * ```
 * 
 * The button cannot be added automatically because the `ObjectEditor` widget
 * cannot know the context it is being used in - in contrast to the edit-actions,
 * that know exaclty if they are to create, edit or copy the data. 
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectEditor extends Form
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter = null) 
    {
        if (parent::hasWidgets() === false) {
            WidgetFactory::createDefaultEditorForObject($this->getMetaObject(), $this);
        }
        return parent::getWidgets($filter);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::hasWidgets()
     */
    public function hasWidgets()
    {
        if (parent::hasWidgets() === false) {
            WidgetFactory::createDefaultEditorForObject($this->getMetaObject(), $this);
        }
        return parent::hasWidgets();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::findChildrenByAttribute()
     */
    public function findChildrenByAttribute(MetaAttributeInterface $attribute)
    {
        if (parent::hasWidgets() === false) {
            WidgetFactory::createDefaultEditorForObject($this->getMetaObject(), $this);
        }
        return parent::findChildrenByAttribute($attribute);
    }
    
    /**
     * Override method here to remove it's UXON annotations and hide the corresponding
     * UXON property. It does not make sense to set widgets for an ObjectEditor
     * explicitly, however the method should still work to ensure interface compoilance!
     * 
     * @see \exface\Core\Widgets\Form::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        return parent::setWidgets($widget_or_uxon_array);
    }
}