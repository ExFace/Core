<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * A ColorIndicator will change it's color depending the value of it's attribute.
 * 
 * Colors can be defined as a simple color scale (like in many other display widgets).
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
 * @author Andrej Kabachnik
 *
 */
class ColorIndicator extends Display implements iHaveColor
{
    const BINDING_PROPERTY_TEXT = 'text';
    
    private $fixedColor = null;
    
    private $fill = true;
    
    private $colorOnly = null;
    
    private $textBindingUxon = null;
    
    private $textBinding = null;
    
    protected function init()
    {
        parent::init();
        $this->textBindingUxon = new UxonObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor($value = null) : ?string
    {
        return $this->fixedColor ?? parent::getColor($value);
    }

    /**
     * Use this fixed color
     * 
     * @uxon-property color
     * @uxon-type color
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->fixedColor = $color;
        return $this;
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
     *
     * @return string
     */
    public function getTextAttributeAlias() : string
    {
        return $this->textAttributeAlias;
    }
    
    /**
     * Makes the progressbar show the value of a different attribute than the one used for the progress.
     *
     * @uxon-property text_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return ColorIndicator
     */
    public function setTextAttributeAlias(string $value) : ColorIndicator
    {
        $this->textBindingUxon->setProperty('attribute_alias', $value);
        $this->textBinding = null;
        return $this;
    }
    
    /**
     * A static text value
     * 
     * @uxon-property text
     * @uxon-type string
     * 
     * @param string $value
     * @return ColorIndicator
     */
    protected function setText(string $value) : ColorIndicator
    {
        $this->textBindingUxon->setProperty('value', $value);
        $this->textBinding = null;
        return $this;
    }
    
    /**
     * 
     * @return WidgetPropertyBindingInterface
     *//*
    public function getTextBinding() : WidgetPropertyBindingInterface
    {
        // Create emtpy binding if none was set explicitly
        if ($this->textBinding === null) {
            $this->textBinding = new WidgetPropertyBinding($this, self::BINDING_PROPERTY_TEXT, $this->textBindingUxon);
        }
        return $this->textBinding;
    }*/
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        //$data_sheet = $this->getTextBinding()->prepareDataSheetToRead($data_sheet);
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
        //$this->getTextBinding()->prefill($data_sheet);
        return;
    }
}