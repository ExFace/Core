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
    
}