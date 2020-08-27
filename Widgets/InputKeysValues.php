<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;

/**
 * Special input widget for key-value structures like JSON, .properties etc.
 * 
 * Instead of showing the raw content, this widget will produce a tabular structure,
 * that is much simpler to handle.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputKeysValues extends InputText
{
    use TranslatablePropertyTrait;
    
    const FORMAT_JSON = 'json';
    
    private $referenceValues = [];
    
    private $captionKey = null;
    
    private $captionValue = null;
    
    private $format = null;
    
    /**
     * If not set explicitly, the width of an options editor will be "max".
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
     * @return array
     */
    public function getReferenceValues() : array
    {
        return $this->referenceValues;
    }
    
    /**
     * Additional data to describe the keys or add example values (in the same format as the action data)
     * 
     * @uxon-property reference_values
     * @uxon-type array
     * @uxon-template {"": {"":""}}
     * 
     * @param array|UxonObject $arrayOrUxon
     * @return InputKeysValues
     */
    public function setReferenceValues($arrayOrUxon) : InputKeysValues
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->referenceValues = $arrayOrUxon->toArray();
        } else {
            $this->referenceValues = $arrayOrUxon;
        }
        
        return $this;
    }
    
    /**
     * How the keys should be called (`Key` by default).
     * 
     * @uxon-property caption_for_keys
     * @uxon-type metamodel:formula|string
     * 
     * @param string $expression
     * @return InputKeysValues
     */
    public function setCaptionForKeys(string $expression) : InputKeysValues
    {
        $this->captionKey = $this->evaluatePropertyExpression($expression);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getCaptionForKeys() : string
    {
        return $this->captionKey ?? 'Key';
    }
    
    /**
     * How the values should be called.
     * 
     * If not set, the name of the input's attribute will be used by default.
     * 
     * @uxon-property caption_for_values
     * @uxon-type metamodel:formula|string
     * 
     * @param string $expression
     * @return InputKeysValues
     */
    public function setCaptionForValues(string $expression) : InputKeysValues
    {
        $this->captionValue = $this->evaluatePropertyExpression($expression);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getCaptionForValues() : ?string
    {
        return $this->captionValue;
    }
    
    /**
     * How to encode the key-value pairs: e.g. JSON, YAML, etc.
     * 
     * Currently only `json` encodeing is supported.
     * 
     * @uxon-property format
     * @uxon-type [json]
     * 
     * @param string $format
     * @return InputKeysValues
     */
    public function setFormat(string $format) : InputKeysValues
    {
        $this->format = $format;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getFormat() : string
    {
        return $this->format ?? self::FORMAT_JSON;
    }
}