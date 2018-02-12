<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Common interface for display widgets: Display, Text, Html, etc.
 * 
 * Display widgets have a value, but do not allow interaction - they are the
 * opposite of input widgets specified by the interface iTakeInput.
 * 
 * @see iTakeInput
 * 
 * @author Andrej Kabachnik
 *
 */
interface iDisplayValue extends iHaveValue
{
    /**
     * Returns TRUE the display should not use any formatting, thus just showing the raw value.
     * @return boolean
     */
    public function getDisableFormatting();
    
    /**
     * Set to TRUE to disable all data type specific formatters for this display.
     *
     * @param boolean $true_or_false
     * @return iHaveValue
     */
    public function setDisableFormatting($true_or_false);
}