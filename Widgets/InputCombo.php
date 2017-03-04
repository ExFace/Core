<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading. It also can optionally accept new values. 
 * 
 * @see InputCombo
 * 
 * @author Andrej Kabachnik
 */
class InputCombo extends InputSelect implements iSupportLazyLoading { 
	private $lazy_loading = true; // Combos should use lazy autosuggest in general
	private $lazy_loading_action = 'exface.Core.Autosuggest';
	private $max_suggestions = 20;
	private $allow_new_values = true;
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->lazy_loading;
	}
	
	/**
	 * By default lazy loading is used to fetch autosuggest values. Set to FALSE to preload the values.
	 * 
	 * @uxon-property lazy_loading
	 * @uxon-type boolean
	 * 
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		$this->lazy_loading = $value;
	}
	
	/**
	 * Returns the alias of the action to be called by the lazy autosuggest.
	 *  
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->lazy_loading_action;
	}
	
	/**
	 * Defines the alias of the action to be called by the autosuggest. Default: exface.Core.Autosuggest.
	 * 
	 * @uxon-property lazy_loading_action
	 * @uxon-type string
	 * 
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_action()
	 */
	public function set_lazy_loading_action($value) {
		$this->lazy_loading_action = $value;
		return $this;
	}
	
	public function get_allow_new_values() {
		return $this->allow_new_values;
	}
	
	/**
	 * By default the InputCombo will also accept values not present in the autosuggest. Set to FALSE to prevent this
	 * 
	 * @uxon-property allow new values
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Widgets\InputCombo
	 */
	public function set_allow_new_values($value) {
		$this->allow_new_values = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	public function get_max_suggestions() {
		return $this->max_suggestions;
	}
	
	/**
	 * Limits the number of suggestions loaded for every autosuggest.
	 * 
	 * @uxon-property max_suggestions
	 * @uxon-type number
	 * 
	 * @param integer $value
	 * @return \exface\Core\Widgets\InputCombo
	 */
	public function set_max_suggestions($value) {
		$this->max_suggestions = intval($value);
		return $this;
	}
	
}
?>