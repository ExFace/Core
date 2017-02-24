<?php namespace exface\Core\Events;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\NameResolver;

/**
 * Action sheet event names consist of the qualified alias of the app followed by "Action" and the respective event type:
 * e.g. exface.Core.ReadData.Action.Perform.Before, etc.
 * @author Andrej Kabachnik
 *
 */
class ActionEvent extends ExfaceEvent {
	private $action = null;
	
	public function get_action() {
		return $this->action;
	}
	
	public function set_action(ActionInterface $action) {
		$this->action = $action;
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Events\ExfaceEvent::get_namespace()
	 */
	public function get_namespace(){
		return $this->get_action()->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . 'Action';
	}
}