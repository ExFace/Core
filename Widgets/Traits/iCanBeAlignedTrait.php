<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

trait iCanBeAlignedTrait {
    
    private $align = null;
    
    /**
     * 
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::getAlign()
     */
    public function getAlign()
    {
        if (is_null($this->align)) {
            return EXF_ALIGN_DEFAULT;
        }
        return $this->align;
    }
    
    /**
     * Sets the alignment within the widget: left, right, center, default or opposite.
     * 
     * If not set, the alignment depends on the specific implementation of the 
     * current template.
     *
     * @uxon-property align
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::setAlign()
     */
    public function setAlign($value)
    {
        if (! defined('EXF_ALIGN_' . mb_strtoupper($value))) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid alignment value "' . $value . '"!', '6W5WLSA');
        }
        $this->align = constant('EXF_ALIGN_' . mb_strtoupper($value));
        return $this;
    }
    
    public function isAlignSet()
    {
        if (is_null($this->align)){
            return false;
        }
        return true;
    }
}