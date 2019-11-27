<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Displays multiple value-widgets in line - e.g. for dimensions (LxWxH), prices (value + currency), etc.
 * 
 * The result looks similar to a single `Value` widget, but instead of showing a single value, it shows
 * multiple side-by-side. Each of the values has less space because the total width of the group is still
 * the same as for a single-value widget. 
 * 
 * Technically, the `InlineGroup` consists of multiple separate `Value` widgets. By default only the caption
 * of the group is shown and each of the grouped widgets has the same width. You can, however, set the width
 * for the contained widgets explicitly to make some wider than others. Setting `hide_caption` to `false`
 * explicitly will also force the `Value` widget show it's individual caption. This can be used to separate
 * the widgets from each other: e.g. by adding `x` characters between inputs for dimensions - see examples
 * below.
 * 
 * Just like many other container widgets, the `InlineGroup` will render it's content as default input widgets 
 * or default display widgets depending on the `readonly` property. This behavior is the same as for for
 * `WidgetGrid`, `WidgetGroup`, etc. Of course, you can also override the `widget_type` of every widget
 * in the group.
 * 
 * ## Examples
 * 
 * ### Dimension input
 * 
 * The following code will produce a numeric input widget, that looks like this: `Dimensions: |_____| x |_____|`.
 * It has the same width as a stand-alone `Input` widget would have, which makes it easy to position such
 * `InlineGroup`s in forms. The caption "Dimensions" comes from the group and the `x` in-between is the
 * custom caption of the second input-widget.
 * 
 * ```
 * {
 *  "widget_type": "InlineGroup",
 *  "caption": "Dimensions",
 *  "widgets": [
 *      {
 *          "attribute_alias": "LENGTH",
 *      },
 *      {
 *          "attribute_alias": "WIDTH",
 *          "caption": "x"
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * ### Dimension display
 * 
 * Similarly to the above input group, a display group can be created as follows. The only difference
 * is `readonly` being set to true for the group: just like in any other container-widget this will result
 * in rendering display-widgets for values instead of inputs. If the `InlineGroup` is part of another
 * readonly-container itslef (e.g. the `DialogHeader`), it will render `Display` widgets automatically.
 * 
 * ```
 * {
 *  "widget_type": "InlineGroup",
 *  "caption": "Dimensions",
 *  "readonly": true,
 *  "widgets": [
 *      {
 *          "attribute_alias": "LENGTH"
 *      },
 *      {
 *          "attribute_alias": "WIDTH",
 *          "caption": "x"
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * ### Input for a value and a unit
 * 
 * Here is how to create an input group with a unit-selector: something like `|_________||___v|`.
 * Note, that the unit-selector will only take up a fifth of the width because of it's width
 * being explicitly set to 20%.
 * 
 * ```
 * {
 *  "widget_type": "InlineGroup",
 *  "caption": "Price",
 *  "widgets": [
 *      {
 *          "attribute_alias": "PRICE"
 *      },
 *      {
 *          "attribute_alias": "CURRENCY",
 *          "widget_type": "InputSelect",
 *          "width": "20%"
 *      }
 *  ]
 * }
 * 
 * ``` 
 *     
 * @author Andrej Kabachnik
 *        
 */
class InlineGroup extends Container
{
    /**
     * Array of widgets to be placed in the group: mostly Value widgets, but any other kind is OK too.
     *
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Value[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        return parent::setWidgets($widget_or_uxon_array);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = NULL)
    {
        if (! $widget instanceof Value) {
            throw new WidgetConfigurationError($this, 'Cannot use widget "' . $widget->getWidgetType() . '" in a ' . $this->getWidgetType() . ': only value-widgets are supported!');
        }
        if ($widget->getHideCaption() === null) {
            $widget->setHideCaption(true);
        }
        return parent::addWidget($widget, $position);
    }
}