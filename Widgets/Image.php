<?php
namespace exface\Core\Widgets;

/**
 * The image widget shows the image specified by the URL in the value of an attribute.
 * 
 * @author Andrej Kabachnik
 *
 */
class Image extends Text {
	
	public function get_uri() {
		return $this->get_value();
	}
	
	public function set_uri($value) {
		return $this->set_value($value);
	}
	
	  
	
}
?>