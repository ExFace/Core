<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

/**
 * Displays multiple value-widgets in line - e.g. for dimensions (LxWxH), prices (value, currency), etc.
 * 
 * The result looks similar to a single `Value` widget, but instead of showing a single value, it shows
 * multiple values side-by-side. Each of the values has less space because the total width of the group is still
 * the same as for a single-value widget. 
 * 
 * Technically, the `InlineGroup` consists of multiple separate `Display` or `Input` widgets. Only the caption
 * of the group is displayed - captions for the individual widgets are hidden. However, their hints will still
 * be visible. If the group has no caption of it's own, the caption of the first widget in the group will be
 * used.
 * 
 * A `separator` can be used to display a character between the grouped widgets: e.g. by adding `x` characters 
 * between inputs for dimensions - see examples below. Alternatively, you can add `Text` widgets to the group
 * manually if you need different separators.
 * 
 * Just like many other container widgets, the `InlineGroup` will render it's content as default input widgets 
 * or default display widgets depending on the `readonly` property. This behavior is the same as for
 * `WidgetGrid`, `WidgetGroup`, etc. Of course, you can also override the `widget_type` of every widget
 * in the group by defining it manually.
 * 
 * ## Examples
 * 
 * ### Dimension input
 * 
 * The following code will produce a numeric input widget, that looks like this: `Dimensions: |_____| x |_____|`.
 * It has the same width as a stand-alone `Input` widget would have, which makes it easy to position such
 * `InlineGroup`s in forms. The caption "Dimensions" comes from the group and the `x` in-between is the
 * `separator`.
 * 
 * ```
 * {
 *  "widget_type": "InlineGroup",
 *  "caption": "Dimensions",
 *  "separator": "x",
 *  "widgets": [
 *      {
 *          "attribute_alias": "LENGTH",
 *      },
 *      {
 *          "attribute_alias": "WIDTH"
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
 *  "separator": "x",
 *  "widgets": [
 *      {
 *          "attribute_alias": "LENGTH"
 *      },
 *      {
 *          "attribute_alias": "WIDTH"
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
    private $separator = '';
    
    private $separatorWidth = '5%';
    
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
    public function addWidget(AbstractWidget $widget, $position = NULL, $addSeparator = true)
    {
        if (! $widget instanceof Value && ! $widget instanceof Filter) {
            throw new WidgetConfigurationError($this, 'Cannot use widget "' . $widget->getWidgetType() . '" in a ' . $this->getWidgetType() . ': only value-widgets are supported!');
        }
        $widget->setHideCaption(true);
        
        if ($this->getSeparator() !== '' && $addSeparator === true && $this->hasWidgets() === true) {
            $this->addWidget($this->createSeparatorWidget(), null, false);
        }
        
        return parent::addWidget($widget, $position);
    }
    
    /**
     *
     * @return string
     */
    protected function getSeparator() : string
    {
        return $this->separator;
    }
    
    /**
     * Delimiter between the group widgets - e.g. "-" for ranges or "x" for dimensions, etc.
     * 
     * @uxon-property separator
     * @uxon-type string
     * 
     * @param string $value
     * @return InlineGroup
     */
    public function setSeparator(string $value) : InlineGroup
    {
        $this->separator = $value;
        if ($this->hasWidgets() === true) {
            $cnt = $this->countWidgets();
            for ($pos = 1; $pos < $cnt; $cnt += 2) {
                $this->addWidget($this->createSeparatorWidget(), $pos);
            }
        }
        return $this;
    }
    
    /**
     * 
     * @return Text
     */
    protected function createSeparatorWidget() : Text
    {
        return WidgetFactory::createFromUxonInParent($this, $this->getSeparatorWidgetUxon());
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getSeparatorWidgetUxon() : UxonObject
    {
        return new UxonObject([
            "widget_type" => "Text",
            "text" => $this->getSeparator(),
            "align" => "center",
            "width" => $this->getSeparatorWidth()
        ]);
    }
    
    /**
     *
     * @return string
     */
    protected function getSeparatorWidth() : string
    {
        return $this->separatorWidth;
    }
    
    /**
     * The width of each separator (defaults to 5%).
     * 
     * @uxon-property separator_width
     * @uxon-type string
     * 
     * @param string $value
     * @return InlineGroup
     */
    public function setSeparatorWidth(string $value) : InlineGroup
    {
        $this->separatorWidth = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        if (parent::getCaption() === null && $this->isEmpty() === false) {
            return $this->getWidgetFirst()->getCaption();
        }
        return parent::getCaption();
    }
}