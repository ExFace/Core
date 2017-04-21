<?php
namespace exface\Core\Widgets;

/**
 * The TextHeading widget can be used for headings. In most HTML-based templates it will get mapped to <h1></h1> or similar.
 * @author Andrej Kabachnik
 *
 */
class TextHeading extends Text {
	private $heading_level = null;
	
	/**
	 * 
	 * @return integer
	 */
	public function get_heading_level() {
		return is_null($this->heading_level) ? 1 : $this->heading_level;
	}
	
	/**
	 * 
	 * @param integer $value
	 * @return \exface\Core\Widgets\TextHeading
	 */
	public function set_heading_level($value) {
		$this->heading_level = $value;
		return $this;
	} 
	
	public function generate_uxon_object(){
		$uxon = parent::generate_uxon_object();
		if (!is_null($this->heading_level)){
			$uxon->set_property('heading_level', $this->get_heading_level());
		}
		return $uxon;
	}
}
?>