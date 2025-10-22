<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\ColorDataType;
use exface\Core\DataTypes\NumberEnumDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveHintScale;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\WidgetPropertyScale;
use exface\Core\Widgets\Traits\iHaveColorTrait;

/**
 * A ColorPalette is a pop up with a palette of colors filled by a color preset list and can be adjusted to offer an additional ColorPicker for more color choices.
 *
 *
 * Colors can be defined via  
 * 
 * - `color_presets_attribute_alias` if the color presets are stored in the data source data type
 * - `color_presets_datatype_alias` if the color presets are stored in a data type
 * - `color_presets` if you want to define your own custom palette  
 * 
 * > [!TIP] If an attribute_alias is defined within the widget, the palette will try to load its color presets from that attribute (default display uxon or it's datatype)  
 *   
 * ## Examples  
 * 
 * ### Simple color palette  
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *   "color_presets": {
 *       "~ERROR",
 *       "~WARNING",
 *       "~OK"
 *   }
 * }
 * 
 * ```
 *
 * ### Color palette with data binding
 *
 * #### Color palette presets from given attribute alias  
 * 
 * If the widget contains an Attribute alias, the color preset will be defined via that attribute.
 * To deactivate this behavior and be able to define your own presets use `prefer_custom_color_presets`!  
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *  "attribute_alias": "Farbe"
 * }
 * ```
 *
 * #### Color palette presets from given data type  
 * 
 * The data type will be used to define the color presets via it's default display uxon containing a color_scale
 * or it's values if the data type defines colors itself.
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *  "color_presets_datatype_alias": "BaumanagementFarben"
 * }
 * 
 * ```
 * 
 * #### Color palette presets from given color attribute alias  
 * 
 * That config overrides the binding with `attribute_alias` and instead binds the presets to the attribute given within this config.
 * The attribute will be used to define the color presets via it's default display uxon containing a color_scale
 * or it's data type.
 * 
 * ```
 * {
 *  "widget_type": "InputColorPalette",
 *  "color_presets_attribute_alias": "Farbe"
 * }
 * 
 * ```
 *
 * ### Custom color scale with color binding  
 * 
 * If you want to override the presets from the color binding use `prefer_custom_color_presets`
 *
 *  ```
 *  {
 *   "widget_type": "InputColorPalette",
 *   "attribute_alias": "Farbe",
 *   "prefer_custom_color_presets": true,
 *    "color_presets": {
 *        "~ERROR",
 *        "~WARNING",
 *        "~OK"
 *    }
 *  }
 *
 *  ```
 *
 * @author Miriam Seitz
 *
 */
class InputColorPalette extends Input implements iHaveHintScale
{
    use iHaveColorTrait;

    const BINDING_PROPERTY_COLOR = 'colors';
    
    private $colorBindingUxon = null;

    private bool $preferCustomColorPresets = false;

    private $hintScale = null;

    private ?WidgetPropertyBinding $colorBinding = null;

    private string $defaultColor = 'transparent';

    private string $displayMode = 'Simplified';

    private bool $showMoreColorsButton = true;

    private bool $showDefaultColorButton = false;

    private $colorPresets;

    protected function init()
    {
        parent::init();
        $this->colorBindingUxon = new UxonObject();
    }

    /**
     * @param $value
     * @return array
     */
    public function getColorPresets($value = null) : array
    {
        if ($this->preferCustomColorPresets) {
            return $this->colorPresets;
        }

        return $this->getColorBinding() !== null ? $this->getColorPresetsFromDataBinding() : $this->colorPresets;
    }
    
    /**
     * The values of this attribute will be used for the color_presets.
     * If not provided the widget look for a color_presets in the given attribute_alias.
     * `color_presets_datatype_alias` and `color_presets` will only be used if the attribute has no color presets within itself.
     *
     * @uxon-property color_presets_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return InputColorPalette
     */
    public function setColorPresetsAttributeAlias(string $value) : InputColorPalette
    {
        $this->colorBindingUxon->setProperty('attribute_alias', $value);
        return $this;
    }
    
    /**
     * Fill the color presets of the color palette with the color presets of a given data type.
     * 
     * Examples:
     * `exface.core.LogLevel` has a color presets within its default display that will then be used for the color palette widget:
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
     * @uxon-property color_presets_datatype_alias
     * @uxon-type string
     *
     */
    public function setColorPresetsDataTypeAlias(string $dataTypeAlias)
    {
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeAlias);
        if ($dataType->getDefaultDisplayUxon()->hasProperty('color_scale')) {
            $this->setColorPresets($dataType->getDefaultDisplayUxon()->getProperty('color_scale'));
        } else {
            $uxon = new UxonObject();
            $uxon->setProperty('color_scale', $dataType->getValues());
            $this->setColorPresets($uxon->getProperty('color_scale'));
        }
        return $this;
    }

    /**
     * Ensures that color_presets does not override a provided color presets from color_presets_datatype_alias
     *
     * @param UxonObject $valueColorPairs
     * @return InputColorPalette
     */
    public function setColorPresets(UxonObject $valueColorPairs)
    {
        if($this->colorPresets === null) {
            $this->colorPresets = $valueColorPairs->toArray();
            ksort($this->colorPresets);
        }

        return $this;
    }

    /**
     * Define if the color_presets properties should be priorities over any given attribute_alias.
     * This is helpful when you use an attribute_alias with a defined color presets via it's datatype but want to override thar with your own presets.
     *
     * @uxon-property prefer_custom_color_presets
     * @uxon-type string
     * @uxon-defaul false
     *
     * @param bool $value
     * @return $this
     */
    public function setPreferCustomColorPresets(bool $value): InputColorPalette
    {
        $this->preferCustomColorPresets = $value;
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
            if ($binding->isEmpty() && $this->preferCustomColorPresets === false && $this->isBoundToAttribute()) {
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

    private function getColorPresetsFromDataBinding() : array
    {
        $binding = $this->getColorBinding();
        $dataType = $binding->getDataType();
        switch (true) {
            // use color scale in attribute default display uxon for color presets
            case $binding->getAttribute()->getDefaultDisplayUxon()->hasProperty('color_scale'):
                $colorPresets = $binding->getAttribute()->getDefaultDisplayUxon()->getProperty('color_scale')->toArray();
                break;
            // load presets via the datatype of the attribute
            // datatype is a color data type
            case $dataType instanceof ColorDataType:
                $colorPresets = $dataType->getColorPresets();
                break;
            // datatype has a display uxon
            case $dataType->getDefaultDisplayUxon()->hasProperty('color_scale'):
                $colorPresets = $dataType->getDefaultDisplayUxon()->getProperty('color_scale')->toArray();
                break;
            // datatype is a color enum (number/string)
            case $dataType instanceof NumberEnumDataType:
                $colorPresets = $dataType->toArray();
                break;
            case $dataType instanceof EnumDataTypeInterface:
                $values = $dataType->getValues();
                $colorPresets = !empty($values) ? array_combine($values, $values) : [];
                break;
            // alright, maybe the value within the attribute alias is a color then?
            default:
                $val = $binding->getValue();
                $colorPresets = !empty($val) ? [$val => $val] : [];
                break;
        }
        ksort($colorPresets);

        // Fallback to defined color presets via uxon color_presets_datatype_alias or color_presets property
        if(empty($colorPresets)) {
            return $this->colorPresets;
        }

        return $colorPresets;
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

    private function hasColorPresets()
    {
        return $this->colorPresets !== null;
    }
}