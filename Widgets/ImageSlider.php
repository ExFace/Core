<?php
namespace exface\Core\Widgets;
class ImageSlider extends DataList {
	private $image_url_column_id = null;
	private $image_title_column_id = null;
	
	public function get_image_url_column_id() {
		return $this->image_url_column_id;
	}
	
	public function set_image_url_column_id($value) {
		$this->image_url_column_id = $value;
		return $this;
	}
	
	public function get_image_title_column_id() {
		return $this->image_title_column_id;
	}
	
	public function set_image_title_column_id($value) {
		$this->image_title_column_id = $value;
		return $this;
	}  
}
?>