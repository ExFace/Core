<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;

/**
 * 
 * @author Andrej Kabachnik
 * 
 * @method ObjectAction[] get_all()
 * @method ObjectActionList|ObjectAction[] getIterator()
 * @method ObjectActionList copy()
 * 
 */
class ObjectActionList extends EntityList {
	
	/**
	 * An object action list stores object actions with their aliases for keys unless the keys are explicitly specified. 
	 * 
	 * {@inheritDoc}
	 * 
	 * @see \exface\Core\CommonLogic\EntityList::add()
	 * @param ObjectAction $object_action
	 */
	public function add($object_action, $key = null){
		if (is_null($key)){
			$key = $object_action->get_alias_with_namespace();
		} 
		return parent::add($object_action, $key);
	}
	
	/**
	 * @return model
	 */
	public function get_model(){
		return $this->get_meta_object()->get_model();
	}	
	
	/**
	 * 
	 * @return Object
	 */
	public function get_meta_object() {
		return $this->get_parent();
	}
	
	/**
	 * 
	 * @param Object $meta_object
	 * @return \exface\Core\CommonLogic\Model\ObjectActionList
	 */
	public function set_meta_object(Object $meta_object) {
		return $this->set_parent($meta_object);
	}	
	
	public function get_used_in_object_basket(){
		$list = clone $this;
		$list->remove_all();
		foreach ($this->get_all() as $object_action){
			if ($object_action->get_use_in_object_basket()){
				$list->add($object_action);
			}
		}
		return $list;
	}
	
}