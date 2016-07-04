<?php namespace exface\Core\Events;

use Symfony\Component\EventDispatcher\Event;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\CommonLogic\NameResolver;

class ExFaceEvent extends Event implements EventInterface {
	private $exface = null;
	private $name = null;
	private $namespace = null;
	
	public function __construct(Workbench &$exface){
		$this->exface = $exface;
	}
	
	public function stop_propagation(){
		$this->stopPropagation();
	}
	
	public function is_propagation_stopped(){
		$this->isPropagationStopped();
	}
	
	public function exface(){
		return $this->exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Events\EventInterface::get_name()
	 */
	public function get_name() {
		return $this->name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Events\EventInterface::set_name()
	 */
	public function set_name($value) {
		$this->name = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Events\EventInterface::get_name_with_namespace()
	 */
	public function get_name_with_namespace(){
		return $this->get_namespace() . NameResolver::NAMESPACE_SEPARATOR . $this->get_name();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Events\EventInterface::get_namespace()
	 */
	public function get_namespace(){
		return 'exface' . NameResolver::NAMESPACE_SEPARATOR . 'Core';
	}
	
	  
}