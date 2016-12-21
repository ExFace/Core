<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Widgets\DebugMessage;

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
			} else {
				// Ignore properties, that cannot be set, as data queries will only import automatically generated UXON and thus will
				// not contain user input, where we would have to look out for wrong parameters.
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
	
	/**
	 * Returns a human-redable description of the data query. 
	 * 
	 * By default it is the corresponding JSON-export of it's UXON-representation, but it is advisable to override this method
	 * to print the actual queries in a format that can be used to reproduce the query with another tool: e.g. SQL-based queries 
	 * should print the SQL (so it can be run through a regular SQL front-end), URL-based queries should print the ready-made
	 * URL, and so on. 
	 * 
	 * @see \exface\Core\Interfaces\iCanBePrinted::to_string()
	 */
	public function to_string(){
		return $this->export_uxon_object()->to_json(true);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::create_debug_widget()
	 */
	public function create_debug_widget(DebugMessage $debug_widget){
		return $debug_widget;
	}
}