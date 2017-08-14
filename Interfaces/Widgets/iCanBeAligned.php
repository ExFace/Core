<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\WidgetInterface;

interface iCanBeAligned extends WidgetInterface
{

    /**
     * Returns the alignment used in this widget (one of the EXF_ALIGN_xxx constants).
     *
     * @return string
     */
    public function getAlign();

    /**
     * Sets the alignment to be used in this widget.
     * Accepts one of the EXF_ALIGN_xxx constants as argument.
     *
     * @param string $value            
     * @throws WidgetPropertyInvalidValueError if the value does not fit one of the constants
     * @return iCanBeAligned
     */
    public function setAlign($value);
    
    /**
     * Returns TRUE if the alignment valie of this widget was set and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isAlignSet();
}