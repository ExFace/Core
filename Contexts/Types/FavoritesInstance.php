<?php namespace exface\Core\Contexts\Types;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\UxonObject;

class FavoritesInstance implements ExfaceClassInterface, iCanBeConvertedToUxon {
	private $exface = null;
	private $meta_object = null;
	private $instance_uid = null;
	private $label = null;
	
	/**
	 * 
	 * @param Object $meta_object
	 * @param string $instance_uid
	 */
	public function __construct(Object &$meta_object, $instance_uid){
		$exface = $meta_object->get_workbench();
		$this->exface = $exface;
		$this->meta_object = $meta_object;
		$this->instance_uid = $instance_uid;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_meta_object(){
		return $this->meta_object;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_instance_uid(){
		return $this->instance_uid;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		// TODO
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		$uxon->set_property($this->get_meta_object()->get_uid_alias(), $this->get_instance_uid());
		$uxon->set_property($this->get_meta_object()->get_label_alias(), $this->get_label());
		return $uxon;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Contexts\Types\FavoritesInstance
	 */
	public function set_label($value) {
		$this->label = $value;
		return $this;
	}  
	
}

?>