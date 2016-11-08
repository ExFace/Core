<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

class DataList extends Data implements iHaveTopToolbar, iHaveBottomToolbar, iFillEntireContainer {
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
		if (parent::get_width()->is_undefined()){
			$this->set_width('max');
		}
		return parent::get_width();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::get_alternative_container_for_orphaned_siblings()
	 */
	public function get_alternative_container_for_orphaned_siblings(){
		return null;
	}
}
?>