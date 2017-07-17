<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Widgets\Button;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;

trait iHaveButtonsTrait 
{
    
    /**
     * Defines the buttons for interaction with data within the widget (e.g. rows in a table, contents of a form, etc.)
     *
     * The array must contain widget objects with widget_type Button or any
     * derivatives. The widget_type can also be ommitted. It is a good idea to
     * only specify an explicit widget type if a special button (e.g. MenuButton)
     * is required. For regular buttons it is advisable to let ExFache choose
     * the right type.
     *
     * Example:
     *  {
     *      "buttons": [
     *          {
     *              "action_alias": "exface.CreateObjectDialog"
     *          },
     *          {
     *              "widget_type": "MenuButton",
     *              "caption": "My menu",
     *              "buttons": [ ... ]
     *          }
     *      ]
     *  }
     *
     * @uxon-property buttons
     * @uxon-type Button[]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $widgets_or_uxon_array)
    {
        foreach ($widgets_or_uxon_array as $b) {
            if ($b instanceof WidgetInterface){
                $this->addButton($b);
            } elseif ($b instanceof UxonObject){
                // FIXME Separating DataButton from Button does not work with MenuButton and ButtonGroup. Not sure, what to do,
                // because the DataButton can be bound to click events while the others can't - thus there is quite a differece.
                // For now, setting the widget type for the button explicitly will allow the user to create non-DataButtons - thus
                // loosing the possibility to use mouse events (which may even be okay, since MenuButtons do not trigger actions
                // by themselves.
                // $button = $this->getPage()->createWidget('DataButton', $this, UxonObject::fromAnything($b));
                if (! $b->hasProperty('widget_type')) {
                    $button = $this->getPage()->createWidget($this->getButtonWidgetType(), $this, $b);
                } else {
                    $button = $this->getPage()->createWidget($b->getProperty('widget_type'), $this, $b);
                    if (! $button->is('Button')) {
                        throw new WidgetConfigurationError($this, 'Invalid widget type "' . $button->getWidgetType() . '" used for button widget!', '6UNT6D5');
                    }
                }
                $this->addButton($button);
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'The "buttons" property of data widgets expects an array of button widgets as input, ' . gettype($b) . ' ' . get_class($b) . ' given instead!');
            }
        }
        return $this;
    }
    
}