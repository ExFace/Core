<?php
namespace exface\Core\CommonLogic;
class WidgetDimension {
	private $exface;
	private $value = NULL;
	
	function __construct(\exface\Core\CommonLogic\Workbench $exface){
		$this->exface = $exface;
	}
	
	/**
	 * Parses a dimension string. Dimensions may be specified in relative ExFace units (in this case, the value is numeric)
	 * or in any unit compatible with the current template (in this case, the value is alphanumeric because the unit must be
	 * specified directltly). 
	 * 
	 * How much a relative unit really is, depends on the template. E.g. a relative height of 1 would mean, that the widget
	 * occupies on visual line in the template (like a simple input), while a relative height of 2 would span the widget over
	 * two lines, etc. The same goes for widths.
	 * 
	 * Examples:
	 * - "1" - relative height of 1 (e.g. a simple input widget). The template would need to translate this into a specific height like 300px or similar.
	 * - "2" - double relative height (an input with double height).
	 * - "0.5" - half relative height (an input with half height)
	 * - "300px" - template specific height defined via the CSS unit "px". This is only compatible with templates, that understand CSS!
	 * - "100%" - percentual height. Most templates will support this directly, while others will transform it to relative or template specific units. 
	 */
	public function parse_dimension($string){
		$this->set_value(trim($string));
	}
	
	public function to_string(){
		return $this->value;
	}
	
	public function get_value() {
		return $this->value;
	}
	
	private function set_value($value) {
		$this->value = ($value === '') ? null : $value;
		return $this;
	}
	
	/**
	 * Returns TRUE if the dimension is not defined (null) or FALSE otherwise.
	 * @return boolean
	 */
	public function is_undefined(){
		if (is_null($this->get_value())) return true;
		else return false;
	}
	
	/**
	 * Returns TRUE if the dimension was specified in relative units and FALSE otherwise.
	 * @return boolean
	 */
	public function is_relative(){
		if (!$this->is_undefined() && (is_numeric($this->get_value()) || $this->get_value() == 'max')) return true;
		else return false;
	}
	
	/**
	 * Returns TRUE if the dimension was specified in template specific units and FALSE otherwise.
	 * @return boolean
	 */
	public function is_template_specific(){
		if (!$this->is_undefined() && !$this->is_percentual() && !$this->is_relative()) return true;
		else return false;
	} 
	
	/**
	 * Returns TRUE if the dimension was specified in percent and FALSE otherwise.
	 * @return boolean
	 */
	public function is_percentual(){
		if (!$this->is_undefined() && substr($this->get_value(), -1) == '%') return true;
		else return false;
	}
}
?>