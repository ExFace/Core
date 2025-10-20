<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\NumberEnumDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Interfaces\Widgets\iHaveHintScale;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Widgets\Parts\WidgetPropertyScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Widgets\Traits\iHaveColorTrait;

/**
 * A ColorPalette will a color selection pop up with all colors provided by a color_scale.
 * 
 * Colors can be defined via
 * 
 * - `color_attribute_alias` if the color scale is stored in the data source data type
 * - `color_scale` (like in many other display widgets).
 * - `color_scale_datatype_alias` if the color scale is stored in a data type
 * 
 * ## Examples
 * 
 * ### Simple color palette
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *   "color_scale": {
 *       "10": "~ERROR",
 *       "99": "~WARNING",
 *       "100": "~OK"
 *   }
 * }
 * 
 * ``` 
 *
 * ### Color palette based on data type with color scale
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *   "color_scale_datatype_alias"'": "BaumanagementFarben"
 * }
 * 
 * ```
 * 
 * ### Color palette based on color attribute alias
 * 
 * You can calculate the value used in a `color_scale` by using a formula in the `color` attribute:
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *  "color_attribute_alias": 'Farbe'
 * }
 * 
 * ```
 *
 * @author Miriam Seitz
 *
 */
class InputColorPalette extends Input implements iHaveColorScale, iHaveHintScale
{
    use iHaveColorTrait;
    use iHaveColorScaleTrait {
        setColorScale as traitSetColorScale;
    }

    const BINDING_PROPERTY_COLOR = 'colors';
    
    private $colorBindingUxon = null;

    private bool $preferCustomColorScale = false;

    private $hintScale = null;

    private ?WidgetPropertyBinding $colorBinding = null;

    private string $defaultColor = 'transparent';

    private string $displayMode = 'Simplified';

    private bool $showMoreColorsButton = true;

    private bool $showDefaultColorButton = false;

    protected function init()
    {
        parent::init();
        $this->colorBindingUxon = new UxonObject();
    }

    /**
     * @param $value
     * @return array
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::getColorScale()
     */
    public function getColorScale($value = null) : array
    {
        if ($this->preferCustomColorScale) {
            return $this->colorScale;
        }

        return $this->getColorBinding()->hasValue() ? $this->getColorScaleFromDataBinding() : $this->colorScale;
    }
    
    /**
     * The values of this attribute will be used for the color_scale.
     * If not provided the widget look for a color_scale in the given attribute_alias.
     * `color_scale_datatype_alias` and `color_scale` will only be used if the attribute has no color scale within itself.
     *
     * @uxon-property color_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return InputColorPalette
     */
    public function setColorAttributeAlias(string $value) : InputColorPalette
    {
        $this->colorBindingUxon->setProperty('attribute_alias', $value);
        return $this;
    }
    
    /**
     * Fill the color scale of the color palette with the color scale of a given data type.
     * 
     * Examples:
     * `exface.core.LogLevel` has a color scale within its default display that will then be used for the color palette widget:
     *  ```
     * {
     * "DEBUG": "transparent",
     * "INFO": "lightblue",
     * "NOTICE": "lightyellow",
     * "WARNING": "yellow",
     * "ERROR": "orange",
     * "CRITICAL": "orangered",
     * "ALERT": "red",
     * "EMERGENCY": "red"
     * }
     * ```
     *
     * Example DataType with colors in its values:
     * `onelink.BMDB.BaumanagementFarben` has colors as it's enum values:
     * ```
     * {
     *  "~OK": "Grün",
     *  "lightgreen": "Hellgrün",
     *  "gold": "Gelb",
     *  "~WARNING": "Orange",
     *  "~ERROR": "Rot",
     *  "lightgray": "Grau",
     *  "lightsteelblue": "Hellblau",
     *  "steelblue": "Dunkelblau"
     * }
     * ```
     * 
     * @uxon-property color_scale_datatype_alias
     * @uxon-type string
     *
     */
    public function setColorScaleDataTypeAlias(string $dataTypeAlias): iHaveColorScale
    {
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeAlias);
        if ($dataType->getDefaultDisplayUxon()->hasProperty('color_scale')) {
            $this->setColorScale($dataType->getDefaultDisplayUxon()->getProperty('color_scale'));
        } else {
            $uxon = new UxonObject();
            $uxon->setProperty('color_scale', $dataType->getValues());
            $this->setColorScale($uxon->getProperty('color_scale'));
        }
        return $this;
    }

    /**
     * Ensures that color_scale does not override a provided color scale from color_scale_datatype_alias
     *
     * @param UxonObject $valueColorPairs
     * @return iHaveColorScale
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::setColorScale()
     */
    public function setColorScale(UxonObject $valueColorPairs): iHaveColorScale
    {
        if($this->colorScale === null) {
            $this->traitSetColorScale($valueColorPairs);
        }

        return $this;
    }

    /**
     * Define if the color_scale properties should be priorities over any given attribute_alias.
     * This is helpful when you use an attribute_alias with a defined color scale via it's datatype but want to override thar with your own scale.
     *
     * @uxon-property prefer_custom_color_scale
     * @uxon-type string
     * @uxon-defaul false
     *
     * @param bool $value
     * @return $this
     */
    public function setPreferCustomColorScale(bool $value): InputColorPalette
    {
        $this->preferCustomColorScale = $value;
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
            // If this widget has a global relation path for all its attributes, pass it to the color
            // binding too!
            if ((null !== $baseRelPath = $this->getAttributeRelationPath()) && ! $uxon->isEmpty() && ! $uxon->hasProperty('attribute_relation_path')) {
                $uxon->setProperty('attribute_relation_path', $baseRelPath);
            }
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

    /**
     * Specify a custom hint scale for the widget.
     *
     * The hint map must be an object with values as keys and CSS hint codes as values.
     * The hint code will be applied to all values between it's value and the previous
     * one. In the below example, all values <= 10 will be red, values > 10 and <= 20
     * will be hinted yellow, those > 20 and <= 99 will have no special hint and values 
     * starting with 100 (actually > 99) will be green.
     *
     * ```
     * {
     *  "10": "This project was not started yet",
     *  "50": "At least one progress report was submitted",
     *  "99" : "Waiting for final approvement from the management",
     *  "100": "All tasks are completed or cancelled"
     * }
     *
     * ```
     *
     * @uxon-property hint_scale
     * @uxon-type string[]
     * @uxon-template {"// <value>": "<hint>"}
     *
     * @param UxonObject $value
     * @return InputColorPalette
     */
    protected function setHintScale(UxonObject $uxon) : InputColorPalette
    {
        $this->hintScale = $uxon;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveHintScaleInterface::getHintScale()
     */
    public function getHintScale() : WidgetPropertyScaleInterface
    {
        if ($this->hintScale === null) {
            $this->hintScale = new WidgetPropertyScale($this, $this->getValueDataType());
        } elseif ($this->hintScale instanceof UxonObject) {
            $uxon = new UxonObject(['scale' => $this->hintScale]);
            $this->hintScale = new WidgetPropertyScale($this, $this->getValueDataType(), $uxon);
        }
        return $this->hintScale;
    }

    private function getColorScaleFromDataBinding() : array
    {
        $binding = $this->getColorBinding();
        $dataType = $binding->getDataType();
        switch (true) {
            // use color scale in attribute default display uxon
            case $binding->getAttribute()->getDefaultDisplayUxon()->hasProperty('color_scale'):
                $values = $binding->getAttribute()->getDefaultDisplayUxon()->getProperty('color_scale')->toArray();
                $scale = $values;
                break;
            case $dataType === null:
                $scale = [];
                break;
            // use color scale within data type of attribute
            // datatype has a display uxon
            case $dataType->getDefaultDisplayUxon()->hasProperty('color_scale'):
                $values = $dataType->getDefaultDisplayUxon()->getProperty('color_scale')->toArray();
                $scale = $values;
                break;
            // datatype is a color enum (number/string)
            case $dataType instanceof NumberEnumDataType:
                $scale = $dataType->toArray();
                break;
            case $dataType instanceof EnumDataTypeInterface:
                $values = $dataType->getValues();
                $scale = !empty($values) ? array_combine($values, $values) : [];
                break;
            // alright, maybe the value within the attribute alias is a color then?
            default:
                $val = $binding->getValue();
                $scale = !empty($val) ? [$val => $val] : [];
                break;
        }
        ksort($scale);

        // Fallback to defined color scale via uxon color_scale_datatype_alias or color_scale property
        if(empty($scale)) {
            return $this->colorScale;
        }

        return $scale;
    }

    /**
     * Choose the default color. If the default color button is shown, clicking it will reset to this color.
     *
     * @uxon-property default_color
     * @uxon-default transparent
     * @uxon-type string
     *
     * @param string $value
     * @return InputColorPalette
     */
    public function setDefaultColor(string $value) : InputColorPalette
    {
        $this->defaultColor = $value;
        return $this;
    }

    public function getDefaultColor(): string
    {
        return $this->defaultColor;
    }

    /**
     * Select the complexity and size of the ColorPicker within "More colors..."
     *
     * @uxon-property display_mode
     * @uxon-default Simplified
     * @uxon-type [Default,Simplified,Large]
     *
     * @param string $value
     * @return InputColorPalette
     */
    public function setDisplayMode(string $value) : InputColorPalette
    {
        $this->displayMode = $value;
        return $this;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    /**
     * Toggle the visibility of a "More colors..." button
     * that will open a ColorPicker for the user to choose any color in the spectrum.
     *
     * @uxon-property show_more_colors_button
     * @uxon-default true
     * @uxon-type boolean
     *
     * @param boolean $value
     * @return InputColorPalette
     */
    public function setShowMoreColorsButton(bool $value) : InputColorPalette
    {
        $this->showMoreColorsButton = $value;
        return $this;
    }

    public function getShowMoreColorsButton(): bool
    {
        return $this->showMoreColorsButton;
    }

    /**
     * Toggle the visibility of a default color button that can be used to reset the color to a default value.
     *
     * @uxon-property show_default_color_button
     * @uxon-default false
     * @uxon-type boolean
     *
     * @param boolean $value
     * @return InputColorPalette
     */
    public function setShowDefaultColorButton(bool $value) : InputColorPalette
    {
        $this->showDefaultColorButton = $value;
        return $this;
    }

    public function getShowDefaultColorButton(): bool
    {
        return $this->showDefaultColorButton;
    }
}