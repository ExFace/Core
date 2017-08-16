<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

trait JqueryAlignmentTrait {

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
                $default_alignment = $this->getTemplate()->getConfig()->getOption('WIDGET.ALL.DEFAULT_ALIGNMENT');
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
}
?>