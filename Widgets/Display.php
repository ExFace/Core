<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iDisplayValue;

/**
 * The Display is the basic widget to show formatted values.
 * 
 * Beside the value itself, the display will also show a title in most templates. In case,
 * the value is empty, it can be replaced by the special text using the property "empty_text".
 * 
 * Templates will format the value automatically based on it's data type. By default, the
 * data type of the underlying meta attribute is used. If no data type can be derived from
 * the meta model, all values will be treated as regular strings.
 * 
 * The data type and, thus, the formatting, can be overridden in the UXON definition of the 
 * Display widget by manually setting the property "data_type".
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class Display extends Value implements iDisplayValue
{
    
}
?>