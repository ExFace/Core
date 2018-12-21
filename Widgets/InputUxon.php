<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * A UXON editor with autosuggest, templates and validation.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputUxon extends InputJson
{
    private $autosuggest = true;
    
    /**
     * Specifies the UXON schema: widget, action, datatype, behavior, etc.
     * 
     * @uxon-property schema
     * @uxon-type [widget,action,behavior,datatype]
     * 
     * @see \exface\Core\Widgets\InputJson::setSchema()
     */
    public function setSchema(string $value) : InputJson
    {
        return parent::setSchema($value);
    }
    
    /**
     * If no schema was set explicitly, the UXON input will use the default schema "uxon".
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputJson::getSchema()
     */
    public function getSchema() : ?string
    {
        return parent::getSchema() ?? 'uxon';
    }
    
    /**
     *
     * @return bool
     */
    public function getAutosuggest() : bool
    {
        return $this->autosuggest;
    }
    
    /**
     * 
     * @param bool|string $value
     * @return InputUxon
     */
    public function setAutosuggest($value) : InputUxon
    {
        $this->autosuggest = BooleanDataType::cast($value);
        return $this;
    }
    
}