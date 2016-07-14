<?php
namespace exface\Core\Widgets;

/**
 * The HTML widget simply shows some HTML. In contrast to a Text widget it will be seamlessly embedded in an HTML-based template
 * and not put into a paragraph as plain text.
 * @author Andrej Kabachnik
 *
 */
class Html extends Text {
	private $css = null;
	private $javascript = null;
	
	public function get_html() {
		return $this->get_text();
	}
	
	public function set_html($value) {
		return $this->set_text($value);
	}
	
	public function get_css() {
		return $this->css;
	}
	
	public function set_css($value) {
		$this->css = $value;
		return $this;
	}	
	
	public function get_javascript() {
		return $this->javascript;
	}
	
	public function set_javascript($value) {
		$this->javascript = $value;
		return $this;
	}  
}
?>