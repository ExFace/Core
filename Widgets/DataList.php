<?php
namespace exface\Widgets;
use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;

class DataList extends Data implements iHaveTopToolbar, iHaveBottomToolbar {
	private $hide_toolbar_top = false;
	private $hide_toolbar_bottom = false;
	
	public function get_hide_toolbar_top() {
		return $this->hide_toolbar_top;
	}
	
	public function set_hide_toolbar_top($value) {
		$this->hide_toolbar_top = $value;
		return $this;
	}
	
	public function get_hide_toolbar_bottom() {
		return $this->hide_toolbar_bottom;
	}
	
	public function set_hide_toolbar_bottom($value) {
		$this->hide_toolbar_bottom = $value;
		return $this;
	}
	
	public function get_hide_toolbars() {
		return ($this->get_hide_toolbar_top() && $this->get_hide_toolbar_bottom());
	}
	
	public function set_hide_toolbars($value) {
		$this->set_hide_toolbar_top($value);
		$this->set_hide_toolbar_bottom($value);
		return $this;
	}
	
	public function get_width(){
		if (!$this->width){
			$this->set_width('max');
		}
		return parent::get_width();
	}
}
?>