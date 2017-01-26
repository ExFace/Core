<?php namespace exface\Core\Interfaces\Widgets;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface iSupportMultiSelect extends iHaveValue {
	
	/**
	 * Returns TRUE if multiselect is enabled for this widget and FALSE otherwise
	 * @return boolean
	 */
	public function get_multi_select();
	
	/**
	 * Set to TRUE to enable multiselect for this widget.
	 * @param boolean $value
	 */
	public function set_multi_select($value);
	
}