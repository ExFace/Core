<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\NumberDataType;

/**
 * A ColorIndicator will change it's color depending the value of it's attribute.
 * 
 * Colors can be defined as a simple `color_scale` (like in many other display widgets). The `color_scale`
 * is applied either to the value of the widget (e.g. defined by `attribute_alias`) or values of `color_attribute_alias`
 * if it is explicitly defined.
 * 
 * ## Examples
 * 
 * ### Simple color scale
 * 
 * ```
 * {
 *  "widget_type": "ColorIndicator",
 *  "color_scale": {
 *      "0": "red",
 *      "50": "yellow",
 *      "100": "green"
 *  }
 * }
 * 
 * ``` 
 *
 * ### Color scale based on an attribute other than the widgets value
 * 
 * ```
 * {
 *  "widget_type": "ColorIndicator",
 *  "attribute_alias": "name",
 *  "color_attribute_alias": "percentage",
 *  "color_scale": {
 *      "0": "red",
 *      "50": "yellow",
 *      "100": "green"
 *  }
 * }
 * 
 * ```
 * 
 * ### Color scale based on a calculation
 * 
 * You can calculate the value used in a `color_scale` by using a formula in the `color` attribute:
 * 
 * ```
 * {
 *  "widget_type": "ColorIndicator",
 *  "calculation": "=Concatenate(TASKS_DONE, ' of ', TASKS_TOTAL)",
 *  "color": "=Percentage(TASKS_DONE, TASKS_TOTAL, 0)",
 *  "color_scale": {
 *      "0": "red",
 *      "50": "yellow",
 *      "100": "green"
 *  }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class ColorIndicator extends Display implements iHaveColor
{
    const BINDING_PROPERTY_COLOR = 'color';
    
    private $fill = true;
    
    private $colorOnly = null;
    
    private $colorBindingUxon = null;
    
    private $colorBinding = null;
    
    protected function init()
    {
        parent::init();
        $this->colorBindingUxon = new UxonObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor($value = null) : ?string
    {
        return $this->getColorBinding()->hasValue() ? $this->getColorBinding()->getValue() : parent::getColor($value);
    }

    /**
     * @return boolean
     */
    public function getFill()
    {
        return $this->fill;
    }

    /**
     * Set to FALSE to only color the value of the widget instead of filling it with color.
     * 
     * @uxon-property fill
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $trueOrFalse
     * @return ColorIndicator
     */
    public function setFill($trueOrFalse)
    {
        $this->fill = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getColorOnly(bool $default = false) : bool
    {
        return $this->colorOnly ?? $default;
    }
    
    /**
     * Set to TRUE/FALSE to display only the color or to color the value respecitvely.
     * 
     * The default depends on the facade used.
     * 
     * @uxon-property color_only
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return ColorIndicator
     */
    public function setColorOnly(bool $value) : ColorIndicator
    {
        $this->colorOnly = $value;
        return $this;
    }
    
    /**
     * The values of this attribute will be used for the color_scale (if it needs to be another attribute than `attribute_alias`)
     *
     * @uxon-property color_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return ColorIndicator
     */
    public function setColorAttributeAlias(string $value) : ColorIndicator
    {
        $this->colorBindingUxon->setProperty('attribute_alias', $value);
        $this->colorBinding = null;
        return $this;
    }
    
    /**
     * A fixed color value or a formula to calculate the color/scale value
     * 
     * Examples:
     * 
     * - `red` - the indicator will always be red
     * - `=Calc(ERROR_FLAG ? 'red', 'green')` - the color will be red if the attribute `ERROR_FLAG` is set and green otherwise
     * - `=Calc(...)` + `color_scale` - if used in combination with the `color_scale` property, the result of the calculation
     * will be concidered to be a value from the color scale.
     * 
     * @uxon-property color
     * @uxon-type color
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($value)
    {
        if ($value instanceof UxonObject) {
            $this->colorBindingUxon = $value;
        } else {
            $this->colorBindingUxon->setProperty('value', $value);
        }
        $this->colorBinding = null;
        return $this;
    }
    
    /**
     * 
     * @return WidgetPropertyBindingInterface
     */
    public function getColorBinding() : WidgetPropertyBindingInterface
    {
        // Create emtpy binding if none was set explicitly
        if ($this->colorBinding === null) {
            $uxon = $this->colorBindingUxon;
            $binding = new WidgetPropertyBinding($this, self::BINDING_PROPERTY_COLOR, $uxon);
            if ($binding->isEmpty() && $this->hasColorScale() && $this->isBoundToAttribute()) {
                $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
                $binding = new WidgetPropertyBinding($this, self::BINDING_PROPERTY_COLOR, $uxon);
            }
            $this->colorBinding = $binding;
        }
        return $this->colorBinding;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        $data_sheet = $this->getColorBinding()->prepareDataSheetToRead($data_sheet);
        return $data_sheet;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        $this->getColorBinding()->prefill($data_sheet);
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Display::isColorScaleRangeBased()
     */
    
    public function isColorScaleRangeBased() : bool
    {
        $dataType = $this->getColorBinding()->getDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
}