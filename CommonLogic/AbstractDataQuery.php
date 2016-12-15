<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * This is the base class for data queries. It includes a default UXON importer.
 * 
 * @author Andrej Kabachnik
 * 
 */
abstract class AbstractDataQuery implements DataQueryInterface {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToString::export_string()
	 */
	public function export_string(){
		return $this->export_uxon_object()->to_json(true);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToString::import_string()
	 */
	public function import_string($string){
	
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		return new UxonObject();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
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
	 * @see \exface\Core\Interfaces\DataSources\DataQueryInterface::count_affected_rows()
	 */
	public function count_affected_rows(){
		return 0;
	}
}