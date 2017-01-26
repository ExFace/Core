<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

interface iCanBeAligned {
	
	/**
	 * Returns the alignment used in this widget (one of the EXF_ALIGN_xxx constants).
	 *  
	 * @return string
	 */
	public function get_align();
	
	/**
	 * Sets the alignment to be used in this widget. Accepts one of the EXF_ALIGN_xxx constants as argument.
	 * 
	 * @param string $value
	 * @throws WidgetPropertyInvalidValueError if the value does not fit one of the constants
	 * @return iCanBeAligned
	 */
	public function set_align($value);
	   
}