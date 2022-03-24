<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateDataType;

/**
 * The Display is the basic widget to show formatted values.
 * 
 * Beside the value itself, the display will also show a title in most facades. In case,
 * the value is empty, it can be replaced by the special text using the property "empty_text".
 * 
 * Facades will format the value automatically based on it's data type. By default, the
 * data type of the underlying meta attribute is used. If no data type can be derived from
 * the meta model, all values will be treated as regular strings.
 * 
 * The data type and, thus, the formatting, can be overridden in the UXON definition of the 
 * Display widget by manually setting the property "data_type".
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class Display extends Value implements iDisplayValue, iHaveColor, iHaveColorScale
{
    use iHaveColorScaleTrait;
    
    /**
     * 
     * @var bool
     */
    private $disableFormatting = false;
    
    /**
     * 
     * @var string
     */
    private $color = null;
    
    /**
     * 
     * @var bool
     */
    private $multiValueListDelimiter = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iDisplayValue::getDisableFormatting()
     */
    public function getDisableFormatting()
    {
        return $this->disableFormatting;
    }
    
    /**
     * Set to TRUE to disable all Formatting for this column (including data type specific ones!) - FALSE by default.
     *
     * @uxon-property disable_formatting
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iDisplayValue::setDisableFormatting()
     */
    public function setDisableFormatting($true_or_false)
    {
        $this->disableFormatting = BooleanDataType::cast($true_or_false);
        return $this;
    }
    
    /**
     * @deprecated use setHiddenIf() instead!
     * 
     * @param bool $value
     * @return Value
     */
    protected function setHideIfEmpty(bool $trueOrFalse) : Value
    {
        $this->setHiddenIf(new UxonObject([
            "value_left" => "=self",
            "comparator" => "==",
            "value_right" => ""
        ]));
        return $this;
    }
    
    /**
     * Returns the color of the text or NULL if no color explicitly defined.
     *
     * {@inheritdoc}
     * @see iHaveColor::getColor()
     */
    public function getColor($value = null) : ?string
    {
        if ($value !== null) {
            return static::findColor($value, $this->getColorScale());
        }
        
        if ($this->hasColorScale() && $this->color === null) {
            return static::findColor($value);
        }
        
        return $this->color;
    }
    
    /**
     * Sets a static color for the content - if not set, facades will use their own color scheme.
     *
     * HTML color names are supported by default. Additionally any color selector supported by
     * the current facade can be used. Most HTML facades will support css colors.
     *
     * @link https://www.w3schools.com/colors/colors_groups.asp
     *
     * @uxon-property color
     * @uxon-type color|string
     *
     * {@inheritdoc}
     * @see iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->color = $color;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (! is_null($this->color)) {
            $uxon->setProperty('color', $this->color);
        }
        if ($this->hasColorScale() === true) {
            $uxon->setProperty('color_scale', new UxonObject($this->getColorScale()));
        }
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased() : bool
    {
        $dataType = $this->getValueDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
    
    /**
     *
     * @return string
     */
    public function getMultiValueListDelimiter() : string
    {
        if ($this->multiValueListDelimiter === null){
            if ($this->isBoundToAttribute() === true){
                $this->multiValueListDelimiter = $this->getAttribute()->getValueListDelimiter();
            } else {
                $this->multiValueListDelimiter = EXF_LIST_SEPARATOR;
            }
        }
        return $this->multiValueListDelimiter;
    }
    
    /**
     * Sets the delimiter to be used to list multiple values.
     *
     * Default: value list delimiter from the value attribute or "," if no value attribute defined.
     *
     * @uxon-property multi_value_list_delimiter
     * @uxon-type string
     * @uxon-default ,
     *
     * @param string $value
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setMultiValueListDelimiter(string $value) : Value
    {
        $this->multiValueListDelimiter = $value;
        return $this;
    }
}
?>