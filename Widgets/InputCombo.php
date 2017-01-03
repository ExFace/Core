<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
/**
 * A combo is similar to a select menu, hovever it must have a search function, supports lazy loading and optionally can accept new input values,
 * which are not present in the initial data set. 
 * @author PATRIOT
 */
class InputCombo extends InputSelect implements iSupportLazyLoading { 
	private $lazy_loading = true; // Combos should use lazy autosuggest in general
	private $lazy_loading_action = 'exface.Core.Autosuggest';
	
	/**
	 * @uxon max_suggestions Number of different suggestions to be displayed at once.
	 * @var integer $max_suggestions
	 */
	private $max_suggestions = 20;
	/**
	 * @uxon allow_new_values Set to FALSE to disallow entery values other than the suggested ones. Defaults to TRUE.
	 * @var boolean $allow_new_values
	 */
	private $allow_new_values = true;
	/**
	 * @uxon value_attribute_alias Alias of the attribute to be used as the internal value of the combo. If not set, the UID of the object in the table will be used
	 * @var string
	 */
	private $value_attribute_alias = null;
	/**
	 * @uxon text_attribute_alias Alias of the attribute to be displayed as the text of the combo. If not set, the label of the object in the table will be used
	 * @var string
	 */
	private $text_attribute_alias = null;
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->lazy_loading;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		$this->lazy_loading = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->lazy_loading_action;
	}
	
	/**
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
	
	public function set_allow_new_values($value) {
		$this->allow_new_values = $value;
		return $this;
	}
	
	public function get_max_suggestions() {
		return $this->max_suggestions;
	}
	
	public function set_max_suggestions($value) {
		$this->max_suggestions = $value;
		return $this;
	}  
	
	public function get_text_attribute_alias() {
		if (is_null($this->text_attribute_alias)){
			if ($this->get_data_object()->get_label_attribute()){
				$this->text_attribute_alias = $this->get_data_object()->get_label_alias();
			} else {
				$this->text_attribute_alias = $this->get_data_object()->get_uid_alias();
			}
		}
		return $this->text_attribute_alias;
	}
	
	/**
	 * Returns the meta object, which the autosuggest data is based on
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	protected function get_data_object(){
		return $this->get_meta_object();
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_text_attribute(){
		return $this->get_meta_object()->get_attribute($this->get_text_attribute_alias());
	}
	
	public function set_text_attribute_alias($value) {
		$this->text_attribute_alias = $value;
		return $this;
	}
	
	public function get_value_attribute_alias() {
		if (is_null($this->value_attribute_alias)){
			$this->value_attribute_alias = $this->get_table_object()->get_uid_alias();
		}
		return $this->value_attribute_alias;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_value_attribute(){
		return $this->get_meta_object()->get_attribute($this->get_value_attribute_alias());
	}
	
	public function set_value_attribute_alias($value) {
		$this->value_attribute_alias = $value;
		return $this;
	}
}
?>