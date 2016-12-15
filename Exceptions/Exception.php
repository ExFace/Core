<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;

class Exception implements ExceptionInterface {
	
	public function export_uxon_object(){
		return new UxonObject();
	}
	
	public function import_uxon_object(UxonObject $uxon){
		foreach ($uxon as $property => $value){
			$method_name = 'set_' . $property;
			if (method_exists($this, $method_name)){
				call_user_func(array($this, $method_name), $value);
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_warning()
	 */
	public function is_warning(){
		if ($this instanceof WarningExceptionInterface){
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_error()
	 */
	public function is_error(){
		return $this->is_warning() ? false : true;
	}
}
?>