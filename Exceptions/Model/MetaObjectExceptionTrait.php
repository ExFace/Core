<?php namespace exface\Core\Exceptions\Model;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\ExceptionTrait;

trait MetaObjectExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $meta_object = null;
	
	public function __construct (Object $meta_object, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_meta_object($meta_object);
	}
	
	public function get_meta_object(){
		return $this->meta_object;
	}
	
	public function set_meta_object(Object $object){
		$this->meta_object = $object;
		return $this;
	}
	
	
}