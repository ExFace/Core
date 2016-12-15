<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;

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
}
?>