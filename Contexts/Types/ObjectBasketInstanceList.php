<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\EntityList;
use exface\Core\CommonLogic\Model\Object;

class ObjectBasketInstanceList extends EntityList {
	
	/**
	 * 
	 * @param string $uid
	 * @param string $label
	 * @return ObjectBasketInstanceList
	 */
	public function add_instance($uid, $label = null){
		$object = $this->get_parent();
		$instance = new ObjectBasketInstance($object, $uid);
		$instance->set_label($label);
		return parent::add($instance, $uid);
	}
	
	/**
	 * 
	 * @return Object
	 */
	public function get_meta_object(){
		return $this->get_parent();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::get()
	 * @return ObjectBasketInstance
	 */
	public function get($instance_uid){
		return parent::get($instance_uid);
	}
	
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		foreach ($this->get_all() as $key => $instance){
			$uxon->set_property($key, $instance->export_uxon_object());
		}
		return $uxon;
	}
	
}

?>