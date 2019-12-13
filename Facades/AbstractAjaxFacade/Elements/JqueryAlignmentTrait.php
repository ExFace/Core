<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

trait JqueryAlignmentTrait {
    
    private $defaultAlign = null;

    /**
     * Calculates the value of the CSS attribute text-align based on the align property of the widget.
     * 
     * @param string $widget_align
     * @param string $default_alignment
     * 
     * @return string
     */
    public function buildCssTextAlignValue($widget_align, $default_alignment = null)
    {        
        if ($widget_align === EXF_ALIGN_DEFAULT || $widget_align === EXF_ALIGN_OPPOSITE){
            if (is_null($default_alignment)){
                $default_alignment = $this->getDefaultAlignment();
            }
            
            if ($widget_align === EXF_ALIGN_DEFAULT){
                return $default_alignment;
            } elseif ($default_alignment === EXF_ALIGN_LEFT){
                return EXF_ALIGN_RIGHT;
            } else {
                return EXF_ALING_LEFT;
            }
        }
        
        return $widget_align;
    }
    
    /**
     * Changes the default alignment in the element (i.e. if no align-property specified for the widget)
     * @param string $value
     * @return self
     */
    public function setDefaultAlignment(string $value) : self
    {
        $this->defaultAlign = $value;
        return $this;
    }
    
    /**
     * Returns the default CSS-align value for this element.
     * 
     * Override this method to change the defaults!
     * 
     * @return string
     */
    protected function getDefaultAlignment() : string
    {
        return $this->defaultAlign ?? $this->getFacade()->getConfig()->getOption('WIDGET.ALL.DEFAULT_ALIGNMENT');
    }
}