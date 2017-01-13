<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
class ObjectBehaviorList extends EntityList implements BehaviorListInterface {
	
	/**
	 * A behavior list will activate every behavior right after it has been added
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::add()
	 * @param BehaviorInterface $behavior
	 */
	public function add($behavior, $key = null){
		if (!$behavior->get_object()->is_exactly($this->get_parent())){
			$behavior->set_object($this->get_parent());
		}
		$result = parent::add($behavior, $key);
		if (!$behavior->is_disabled()){
			$behavior->register();
		}
		return $result;
	}
	
	/**
	 * @return Object
	 */
	public function get_object() {
		return $this->get_parent();
	}
	
	/**
	 * 
	 * @param string $qualified_alias
	 * @return BehaviorInterface
	 */
	public function get_by_alias($qualified_alias){
		foreach ($this->get_all() as $behavior){
			if (strcasecmp($behavior->get_alias_with_namespace(), $qualified_alias) == 0){
				return $behavior;
			}
		}
		return false;
	}
	
	/**
	 * 
	 * @param Object $value
	 * @return ObjectBehaviorList
	 */
	public function set_object(Object $value) {
		$this->set_parent($value);
		return $this;
	} 	
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::set_parent()
	 * @param Object
	 */
	public function set_parent($object){
		$result = parent::set_parent($object);
		foreach ($this->get_all() as $behavior){
			$behavior->set_object($object);
		}
		return $result;
	}
}