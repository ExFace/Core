<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Actions\ActionInterface;

trait ActionExceptionTrait {
	
	private $action = null;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
	 */
	public function __construct (ActionInterface $action, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
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
	 * @see @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::set_action()
	 */
	public function set_action(ActionInterface $value){
		$this->action = $value;
		return $this;
	}
	
}
?>