<?php namespace exface\Core\Interfaces\Widgets;

/**
 * This interface is meant for container widgets, which take care of positioning their contents according
 * to certaine layout rules: e.g. the panel.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iLayoutWidgets extends iContainOtherWidgets {
	
	/**
	 * Returns the number of columns in the layout
	 * 
	 * @return integer
	 */
	public function get_column_number();
	
	/**
	 * Set the number of columns in the layout
	 * 
	 * @param integer $value
	 */
	public function set_column_number($value);
	
	/**
	 * Returns TRUE if the columns should be stacked on small screens and FALSE otherwise. Returns NULL if the creator of the widget
	 * had made no preference and thus the stacking is completely upto the template.
	 * 
	 * @return boolean
	 */
	public function get_column_stack_on_smartphones();
	
	/**
	 * Determines wether columns should be stacked on smaller screens (TRUE) or left side-by-side (FALSE). Setting this to NULL will
	 * leave it upto the template to decide.
	 * 
	 * @param boolean $value
	 */
	public function set_column_stack_on_smartphones($value);
	
	/**
	 * Returns TRUE if the columns should be stacked on midsize screens and FALSE otherwise. Returns NULL if the creator of the widget
	 * had made no preference and thus the stacking is completely upto the template.
	 * 
	 * @return boolean
	 */
	public function get_column_stack_on_tablets();
	
	/**
	 * Determines wether columns should be stacked on midsize screens (TRUE) or left side-by-side (FALSE). Setting this to NULL will
	 * leave it upto the template to decide.
	 * 
	 * @param boolean $value
	 */
	public function set_column_stack_on_tablets($value);
	  
}