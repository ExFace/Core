<?php namespace exface\Core;

use exface\exface;
use exface\Core\UxonObject;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Model\Object;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\NameResolverInterface;

/**
 * 
 * @author aka
 * 
 */
abstract class AbstractBehavior implements BehaviorInterface {
	private $object = null;
	private $behavior = null;
	private $disabled = false;
	private $registered = false;
	private $name_resolver = false;
	
	public function __construct(Object &$object){
		$this->set_object($object);
	}
	
	/**
	 * @return NameResolverInterface
	 */
	public function get_name_resolver() {
		return $this->name_resolver;
	}
	
	public function set_name_resolver($value) {
		$this->name_resolver = $value;
		return $this;
	}
	
	public function get_alias(){
		return $this->get_name_resolver()->get_alias();
	}
	
	public function get_alias_with_namespace(){
		return $this->get_name_resolver()->get_alias_with_namespace();
	}
	
	public function get_namespace(){
		return $this->get_name_resolver()->get_namespace();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::get_object()
	 */
	public function get_object() {
		return $this->object;
	}
	
	public function set_object(Object &$value) {
		$this->object = $value;
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 * @return exface
	 */
	public function exface(){
		return $this->get_object()->exface();
	}
	
	public function import_uxon_object(UxonObject $uxon){
		$uxon->import_to_instance($this);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = new UxonObject();
		$uxon->set_property('disabled', $this->is_disabled());
		return $uxon;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::activate()
	 */
	abstract public function register();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::is_disabled()
	 */
	public function is_disabled(){
		return $this->disabled;
	}
	
	/**
	 * This method does the same as enable() and disable(). It is important to be able to import UXON objects.
	 * @param boolean
	 * @return BehaviorInterface
	 */
	public function set_disabled($value){
		$this->disabled = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::disable()
	 */
	public function disable(){
		$this->disabled = true;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::enable()
	 */
	public function enable(){
		if (!$this->is_registered()){
			$this->register();
		}
		$this->disabled = false;
		return $this;
	}
	
	/**
	 * Marks the behavior as registered. is_registered() will now return true. This is a helper method for
	 * the case, if you don't want to override the is_registered() method: just call set_registered() in
	 * your register() implementation!
	 * @param boolean $value
	 * @return BehaviorListInterface
	 */
	protected function set_registered($value){
		$this->registered = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Model\BehaviorInterface::is_registered()
	 */
	public function is_registered(){
		return $this->registered;
	}
	
	/**
	 * Returns a copy of the Behavior without 
	 * @see \exface\Core\Interfaces\iCanBeCopied::copy()
	 * @return BehaviorInterface
	 */
	public function copy(){
		return clone $this;
	}
	
}