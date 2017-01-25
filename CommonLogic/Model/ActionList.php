<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 * @method ActionInterface[] get_all()
 * @method ActionList|ActionInterface[] getIterator()
 * @method ActionInterface get()
 * @method ActionInterface get_first()
 * @method ActionInterface get_last()
 * @method ActionList copy()
 * 
 */
class ActionList extends EntityList {
		
	/**
	 * An action list stores actions with their aliases for keys unless the keys are explicitly specified. 
	 * 
	 * {@inheritDoc}
	 * 
	 * @see \exface\Core\CommonLogic\EntityList::add()
	 * @param ActionInterface $action
	 */
	public function add($action, $key = null){
		if (is_null($key)){
			$key = $action->get_alias_with_namespace();
		} 
		return parent::add($action, $key);
	}	
	
}