<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\JsonDataType;

/**
 * A special text input widget for JSON editing.
 * 
 * Facades may have a WYSIWYG editor for JSON or should fall back to a regular
 * text input.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputJson extends InputText
{
    private $schema = null;
    
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
     * 
     * @return string|NULL
     */
    public function getSchema() : ?string
    {
        if ($this->schema === null) {
            if ($this->getValueDataType() instanceof JsonDataType) {
                $this->schema = $this->getValueDataType()->getSchema();
            }
        }
        return $this->schema;
    }
    
    /**
     * URI for the JSON schema to be used for validation in the widget.
     * 
     * @uxon-property schema
     * @uxon-type string
     * 
     * @param string|NULL $value
     * @return InputJson
     */
    public function setSchema(string $value) : InputJson
    {
        $this->schema = $value;
        return $this;
    }
    
}