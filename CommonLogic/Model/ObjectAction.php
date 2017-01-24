<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;

class ObjectAction implements ExfaceClassInterface, AliasInterface {
	
	private $object = null;
	private $app = null;
	private $alias = null;
	private $name = null;
	private $description_short = null;
	private $action = null;
	private $action_uxon = null;
	private $aciton_config_read = false;
	private $use_in_object_basket = false;
	
	/**
	 * 
	 * @param Object $object
	 */
	public function __construct(Object $object){
		$this->object = $object;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_meta_object(){
		return $this->object;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->get_meta_object()->get_workbench();
	}
	
	public function get_app() {
		return $this->app;
	}
	
	public function set_app(AppInterface $value) {
		$this->app = $value;
		return $this;
	}
	
	public function get_alias() {
		return $this->alias;
	}
	
	public function set_alias($value) {
		$this->alias = $value;
		return $this;
	}
	
	public function get_namespace(){
		return $this->get_app()->get_alias_with_namespace();
	}
	
	public function get_alias_with_namespace(){
		return $this->get_namespace() . NameResolver::NAMESPACE_SEPARATOR . $this->get_alias();
	}
	
	public function get_action() {
		if (!$this->aciton_config_read){
			$this->action->import_uxon_object($this->get_action_uxon());
			$this->aciton_config_read = true;
		}
		return $this->action;
	}
	
	public function set_action($alias_or_class_or_file) {
		$this->action = ActionFactory::create_from_string($this->get_workbench(), $alias_or_class_or_file);
		return $this;
	}
	
	/**
	 * 
	 * @return UxonObject
	 */
	public function get_action_uxon() {
		return $this->action_uxon;
	}
	
	/**
	 * 
	 * @param string|\stdClass|UxonObject $value
	 * @return \exface\Core\CommonLogic\Model\ObjectAction
	 */
	public function set_action_uxon($value) {
		$this->action_uxon = UxonObject::from_anything($value);
		$this->aciton_config_read = false;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_use_in_object_basket() {
		return $this->use_in_object_basket;
	}
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\CommonLogic\Model\ObjectAction
	 */
	public function set_use_in_object_basket($value) {
		$this->use_in_object_basket = $value ? true : false;
		return $this;
	}
	 
	public function get_name() {
		if (is_null($this->name)){
			return $this->get_action()->get_name();
		}
		return $this->name;
	}
	
	public function set_name($value) {
		$this->name = $value;
		return $this;
	}
	
	public function get_description_short() {
		return $this->description_short;
	}
	
	public function set_description_short($value) {
		$this->description_short = $value;
		return $this;
	}
	    
}