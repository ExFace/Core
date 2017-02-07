<?php namespace exface\Core\CommonLogic\Model;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
class ObjectActionList extends ActionList {
	
	private $object_basket_action_aliases = array();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\Model\ActionList::add()
	 */
	public function add($action, $key = null){
		$action->set_meta_object($this->get_meta_object());
		return parent::add($action, $key);
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
		foreach ($this->get_all() as $action){
			if (in_array($action->get_alias_with_namespace(), $this->get_object_basket_action_aliases())){
				$list->add($action);
			}
		}
		return $list;
	}
	
	/**
	 *
	 * @return string[]
	 */
	public function get_object_basket_action_aliases() {
		return $this->object_basket_action_aliases;
	}
	
	/**
	 *
	 * @param array $value
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function set_object_basket_action_aliases(array $value) {
		$this->object_basket_action_aliases = $value;
		return $this;
	}
	
}