<?php
namespace exface\Core\Widgets;

/**
 * The TextHeading widget can be used for headings. In most HTML-based templates it will get mapped to <h1></h1> or similar.
 * @author aka
 *
 */
class TextHeading extends Text {
	private $heading_level = 1;
	
	public function get_heading_level() {
		return $this->heading_level;
	}
	
	public function set_heading_level($value) {
		$this->heading_level = $value;
		return $this;
	}  
}
?>