<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * This is a special text input widget for JSON editing.
 * Templates may have a WYSIWYG editor for JSON or should fall back to a regular
 * text input
 *
 * @author Andrej Kabachnik
 *        
 */
class InputJson extends InputText
{
    private $formattedJsonExport = false;
    
    /**
     * If not set explicitly, the width of a JSON editor will be "max".
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth()
    {
        $width = parent::getWidth();
        if ($width->isUndefined()) {
            $width->parseDimension('max');
        }
        return $width;
    }
    
    /**
     * Returns true if the JSON exported from the widget should be formatted in human-readable form, false otherwise.
     * 
     * @return boolean
     */
    public function isFormattedJsonExport()
    {
        return $this->formattedJsonExport;
    }
    
    /**
     * Set to true to make the widget export JSON in a human readable form (line-breaks, intendations).
     * 
     * default: false
     * 
     * e.g:
     * false:
     * {"key1":"value1","key2":"value2"}
     * 
     * true:
     * {
     *     "key1": "value1",
     *     "key2": "value2"
     * }
     * 
     * @uxon-property formatted_json_export
     * @uxon-type boolean
     * 
     * @param boolean $value
     * @return InputJson
     */
    public function setFormattedJsonExport($value)
    {
        $this->formattedJsonExport = BooleanDataType::cast($value);
        return $this;
    }
}
?>