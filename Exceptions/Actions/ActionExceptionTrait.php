<?php namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output action specific debug information. 
 * 
 * @author Andrej Kabachnik
 *
 */
trait ActionExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $action = null;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
	 */
	public function __construct (ActionInterface $action, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_action($action);
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::get_action()
	 */
	public function get_action(){
		return $this->action;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::set_action()
	 */
	public function set_action(ActionInterface $value){
		$this->action = $value;
		return $this;
	}
	
}
?>