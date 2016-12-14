<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * This is the base class for data queries. It includes a default UXON importer.
 * 
 * @author Andrej Kabachnik
 * 
 */
abstract class AbstractDataQuery implements DataQueryInterface {
	
	public function export_string(){
		return '';
	}
	
	public function import_string($string){
	
	}
	
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