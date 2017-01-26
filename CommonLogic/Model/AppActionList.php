<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\AppInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
class AppActionList extends ActionList {
	
	public function get_app() {
		return $this->get_parent();
	}
	
	public function set_app(AppInterface $value) {
		$this->set_parent($value);
		return $this;
	} 
	
}